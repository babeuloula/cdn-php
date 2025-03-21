-include docker/.env

.PHONY: build

NO_RESET ?= n
NO_CLEAR_CACHE ?= n
COVERAGE_CHECKER ?= y
.SILENT: shell analyse phpunit
.DEFAULT_GOAL := install

##
## Project
##---------------------------------------------------------------------------

# Install the project
install: hooks
	cd ./docker && ./install.sh

# Start the project
start: hooks
	cd ./docker && ./start.sh

# Stop the project
stop:
	cd ./docker && ./stop.sh

# Destroy the stack
destroy: stop
	cd ./docker && docker compose rm -f
	rm -rf ./.cache/minio

# Restart the project
restart: stop start

# Read logs
logs:
	cd ./docker && docker compose logs -f

hooks:
	# Pre commit
	echo "#!/bin/bash" > .git/hooks/pre-commit
	echo "DISABLE_TTY=1 make test-phpcs" >> .git/hooks/pre-commit
	chmod +x .git/hooks/pre-commit
	# Git pull
	echo "#!/bin/bash" > .git/hooks/post-merge
	echo "DISABLE_TTY=1 make post-merge" >> .git/hooks/post-merge
	chmod +x .git/hooks/post-merge

post-merge: composer

composer:
	docker/exec composer install --optimize-autoloader --no-interaction

# Connect to PHP container
shell:
	docker/exec

# Reset all the application
zero: buckets

##
## S3
##---------------------------------------------------------------------------

# Delete and create buckets
buckets:
	cd docker && docker compose run --rm minio_mc rb local/${S3_BUCKET_NAME} --force || true
	cd docker && docker compose run --rm minio_mc mb local/${S3_BUCKET_NAME} --region ${S3_REGION} --ignore-existing
	cd docker && docker compose run --rm minio_mc policy set download local/${S3_BUCKET_NAME}

##
## Code quality
##---------------------------------------------------------------------------

# Execute tests suite
test: test-phpcs test-phpstan test-phpmd test-phpunit test-security

# Execute PHPCS
test-phpcs:
	mkdir -p ./.cache/phpcs
	docker/exec vendor/bin/phpcs -p --report-full --report-checkstyle=./.cache/phpcs/phpcs-report.xml

# Execute PHPCS fixer
fix-phpcs:
	docker/exec vendor/bin/phpcbf -p

# Execute PHPStan
test-phpstan:
	docker/exec vendor/bin/phpstan analyse --memory-limit=4G

# Execute PHP Mess Detector
test-phpmd:
	docker/exec vendor/bin/phpmd src ansi phpmd-ruleset.xml

# Execute PHPUnit
test-phpunit:
	mkdir -p ./.cache/phpunit
	docker/exec vendor/bin/phpunit \
		--cache-directory=./.cache/phpunit \
		--coverage-html=./.cache/phpunit/coverage \
		--coverage-xml=./.cache/phpunit/coverage/xml
	docker/exec vendor/bin/coverage-checker ./.cache/phpunit/coverage/xml/index.xml 95

# Check CVE for vendor dependencies
test-security:
	docker/exec composer audit

##
## Build & deploy
##---------------------------------------------------------------------------

build:
	rm -rf ./build
	mkdir -p ./build
	cp composer.json ./build/composer.json
	cp composer.lock ./build/composer.lock
	cp -r public ./build/public
	cp -r src ./build/src
	docker/exec composer install --no-dev --optimize-autoloader --no-interaction --no-progress --working-dir=build
	rm -f ./build/composer.*
	cd ./build && zip -rq ./../latest.zip ./*
	mv ./latest.zip ./build/latest.zip

deploy:
	docker/exec composer install --no-dev --optimize-autoloader --no-interaction --no-progress
	serverless deploy --force

remove:
	serverless remove

deploy-prod:
	docker/exec composer install --no-dev --optimize-autoloader --no-interaction --no-progress
	serverless deploy --stage=prod --force

remove-prod:
	serverless remove --stage=prod
