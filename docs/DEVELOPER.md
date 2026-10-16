# Developer documentation

## IDE
Use an IDE you like, but check if it can handle Markdown and Mermaid correctly.

## Commands

### NPM
```
npm run dev
npm run lint
npm run lint:fix
npm run build
npm run build:code
npm run build:translations
```

### PHPStan (PHP TEsting framework)
```
vendor/phpstan/phpstan/phpstan
```

### Nextclous
```
php occ files:cleanup
```

## Requirements

- **NodeJS:** v24.x
- **NPM:** v10.x to 11.x
- Ev. **Docker:** (Integration-Tests)
    - [https://docs.docker.com/engine/install/debian/]

## On boarding

- Clone repository from gitlab ```git clone https://github.com/IdentSpace/sticky_crm.git```
- Do an ```npm i``` to install all packages - use ```npm i --force``` if you get some dependency errors
- Try ```npm run build:code``` and ```npm run build:translations```
- If you have a local Nextcloud instance running (Linux):
    - Add symbolic link ```ln -s <source dir> <target dir>``` where source dir is your cloned repository and target dir is in your www/nextcloud/apps folder e.g. ```ln -s /home/user/sticky_crm /var/www/nextcloud/apps/sticky_crm```
    - Ensure that www-data has write access to this directory e.g. /home/user/sticky_crm
    - Now you should be able to activate the app sticky_crm in the Nextcloud
    - Download and install composer (PHP) from https://getcomposer.org/
    - Execute ```composer i```
- To run integration tests or release you need docker to be installed:
    - Execute ```./tests/integration/run.sh``` (be sure your local acoount is member of the docker group or run the command as root)
    - Execute ```./tests/integration/local_run_all.sh``` to run all integration test of Github locally

## Testing
### Local
* ```npm run lint``` -> Runs linting test on src directory
* ```npm run test:phpstan``` -> Runs PHPStan on src directory
* ```npm run test:integration``` -> Runs integration tests with different variations on docker
* ```npm run test``` -> Runs build and ALL tests
### Github
* build -> Test building
* lint -> Runs linting test on src directory
* PHPStan -> Runs PHPStan on src directory
* integration -> Runs integration tests with different variations on docker
