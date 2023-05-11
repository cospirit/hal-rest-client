.SILENT:

### User defined functions ###

check_defined = \
	$(foreach 1,$1,$(__check_defined))
__check_defined = \
	$(if $(value $1),, \
	  $(error Undefined $1$(if $(value 2), ($(strip $2))). Is associated '.env' file with env vars exist ?))
##############################

USER_ID=`id -u`
GROUP_ID=`id -g`

# 1. filter main receipe name from args (https://stackoverflow.com/a/47008498)
# 2. exclude 'development@install' in remaining args for receipe chaining
# 3. exclude 'development@update' in remaining args for receipe chaining
# TODO find a better way to manage this exception
ifneq "$(or $(filter development@install,$(MAKECMDGOALS)), $(filter development@update,$(MAKECMDGOALS)))" ""
	args := `arg="$(filter-out $@,$(MAKECMDGOALS))" && echo $${arg:-${1}}`
	args := $(subst development@install,,$(args))
	args := $(subst development@update,,$(args))
else
	args = `arg="$(filter-out $@,$(MAKECMDGOALS))" && echo $${arg:-${1}}`
endif

# this target avoid make to return an error of unknown target when an extra argument is passed
# https://stackoverflow.com/a/47008498
%:
	:

development@install-git-hooks:
	if [ ! -d "/tmp/dev-stack" ]; then \
		mkdir -p /tmp/dev-stack && cd /tmp/dev-stack && \
		git init -q && \
		git remote add origin git@github.com:cospirit/dev-stack.git && \
		git config core.sparseCheckout true && \
		echo "git-hooks/" >> .git/info/sparse-checkout; \
	fi
	cd /tmp/dev-stack && git pull -q origin master
	cp /tmp/dev-stack/git-hooks/* .git/hooks/;

### Development Composer ###

development@composer.install:
	docker-compose exec -u app app \
		composer install \
			--prefer-dist \
			--optimize-autoloader \
			--no-interaction \
			--verbose \
			--ansi

development@composer.update:
	docker-compose exec -u app app \
		composer update \
			--no-interaction \
			--prefer-dist \
			--verbose \
			$(call args, '')

### CS Fixer ###

development@php-cs-fixer.fix:
	docker-compose exec -u app app bin/php-cs-fixer fix $(call args, '')

development@php-cs-fixer.diff:
	docker-compose exec -u app app bin/php-cs-fixer fix --diff --dry-run $(call args, '')

### Development Docker ###

development@sh:
	docker-compose exec -u app app bash

development@env:
	echo "\n###> Docker ###\nUSER_ID=$(USER_ID)\nGROUP_ID=$(GROUP_ID)\n###< Docker ###" > system.env

development@up:
	docker-compose up -d

development@down:
	docker-compose down --remove-orphans

development@restart: development@down development@up

development@state:
	docker-compose ps

development@install: development@env development@restart development@application.install development@state development@install-git-hooks

development@update: development@restart development@application.install development@state

development@application.install: development@composer.install

###############################
###         Tests           ###
###############################

test@phpunit:
	docker-compose run --rm app bin/phpunit --stop-on-failure --stop-on-error
