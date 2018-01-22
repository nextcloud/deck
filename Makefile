app_name=$(notdir $(CURDIR))
build_tools_directory=$(CURDIR)/build/tools
build_dir=$(CURDIR)/build/artifacts
source_build_directory=$(CURDIR)/build/artifacts/source
source_package_name=$(source_build_directory)/$(app_name)
appstore_build_directory=$(CURDIR)/build/artifacts/appstore
appstore_package_name=$(appstore_build_directory)/$(app_name)
npm=$(shell which npm 2> /dev/null)
composer=$(shell which composer 2> /dev/null)

sign_dir=$(build_dir)/sign
cert_dir=$(HOME)/.nextcloud/certificates


default: build

clean-build:
	rm -rf $(build_dir)

clean-dist:
	rm -rf js/node_modules

install-deps:
	cd js && npm install

build: build-js

build-js: install-deps
	cd js && ./node_modules/.bin/webpack --config webpack.prod.config.js

watch:
	cd js && ./node_modules/.bin/webpack --config webpack.dev.config.js --watch

# appstore: clean install-deps
appstore: clean-build build
	rm -rf $(appstore_build_directory)
	mkdir -p $(appstore_build_directory)
	tar cvzf $(appstore_package_name).tar.gz \
	--exclude="../$(app_name)/build" \
	--exclude="../$(app_name)/tests" \
	--exclude="../$(app_name)/Makefile" \
	--exclude="../$(app_name)/*.log" \
	--exclude="../$(app_name)/phpunit*xml" \
	--exclude="../$(app_name)/composer.*" \
	--exclude="../$(app_name)/js/node_modules" \
	--exclude="../$(app_name)/js/tests" \
	--exclude="../$(app_name)/js/test" \
	--exclude="../$(app_name)/js/*.log" \
	--exclude="../$(app_name)/js/package-lock.json" \
	--exclude="../$(app_name)/js/package.json" \
	--exclude="../$(app_name)/js/bower.json" \
	--exclude="../$(app_name)/js/karma.*" \
	--exclude="../$(app_name)/js/protractor.*" \
	--exclude="../$(app_name)/package.json" \
	--exclude="../$(app_name)/bower.json" \
	--exclude="../$(app_name)/karma.*" \
	--exclude="../$(app_name)/protractor\.*" \
	--exclude="../$(app_name)/.*" \
	--exclude="../$(app_name)/*.lock" \
	--exclude="../$(app_name)/run-eslint.sh" \
	--exclude="../$(app_name)/js/.*" \
	--exclude="../$(app_name)/vendor" \
	--exclude-vcs \
	 ../$(app_name)


	@if [ -f $(cert_dir)/$(app_name).key ]; then \
		echo "Signing package…"; \
		openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key $(build_dir)/$(app_name).tar.gz | openssl base64; \
	fi

	echo $(appstore_package_name).tar.gz

test: test-unit test-integration

test-unit:
	mkdir -p build/
ifeq (, $(shell which phpunit 2> /dev/null))
	@echo "No phpunit command available, downloading a copy from the web"
	mkdir -p $(build_tools_directory)
	curl -sSL https://phar.phpunit.de/phpunit-5.7.phar -o $(build_tools_directory)/phpunit.phar
	php $(build_tools_directory)/phpunit.phar -c tests/phpunit.xml --coverage-clover build/php-unit.coverage.xml
	php $(build_tools_directory)/phpunit.phar -c tests/phpunit.integration.xml --coverage-clover build/php-integration.coverage.xml
else
	phpunit -c tests/phpunit.xml --coverage-clover build/php-unit.coverage.xml
	phpunit -c tests/phpunit.integration.xml --coverage-clover build/php-integration.coverage.xml
endif

test-integration:
	cd tests/integration && ./run.sh

test-js: install-deps
	cd js && run test

