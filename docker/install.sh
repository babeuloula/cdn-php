#!/usr/bin/env bash

set -e

readonly DOCKER_PATH=$(dirname $(realpath $0))
cd ${DOCKER_PATH};

. ./lib/functions.sh

block_info "Welcome to CDN PHP installer!"

parse_env ".env.dist" ".env"
. ./.env
echo -e "${GREEN}Configuration done!${RESET}" > /dev/tty

block_info "Build & start Docker"
# Pull all container in parallel to optimize your time
docker compose pull
# Build all container in parallel to optimize your time
docker compose build --parallel
./stop.sh
./start.sh
echo -e "${GREEN}Docker is started with success!${RESET}" > /dev/tty

block_info "Install dependencies"
mkdir -p ../.cache/driver/s3
install_composer $(dirname ${DOCKER_PATH})
echo -e "${GREEN}Dependencies installed with success!${RESET}" > /dev/tty

block_info "Prepare CDN PHP"
minio

block_success "CDN PHP is started http://localhost:8000"
