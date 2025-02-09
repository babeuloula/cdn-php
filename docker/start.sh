#!/usr/bin/env bash

set -e

readonly DOCKER_PATH=$(dirname $(realpath $0))
cd ${DOCKER_PATH};

. ./lib/functions.sh
parse_env ".env.dist" ".env"
. ./.env

# Start and remove useless containers
docker compose up -d --remove-orphans --force-recreate
