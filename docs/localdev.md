# Local Development Setup: Drupal CMS + DDEV

This guide explains how to set up the Drupal CMS project locally using DDEV for containerized
development.

The local site will be https://iamhere.local. The production site is https://iamhere.social.

## Prerequisites
-------------

Before getting started, make sure you have the following installed:
- Docker: https://docs.docker.com/get-docker/
- DDEV: https://ddev.readthedocs.io/en/stable/#installation

On macOS, you can install both with Homebrew:
```shell
brew install ddev
```

## Set Up the Local Site
---------------------

1. Clone the repository:
```shell
git clone <your-repo-url> iamhere
cd iamhere
```
2. Start the DDEV environment, this will build the containers and configure the local project:
```shell
ddev start
```
3. Create your local installation as follows:
```shell
drush site:install drupal_cms_installer -y
drush recipe ../recipes/custom/iamhere
```

## Export new config

If you change configuration locally, you should export it using
```shell
ddev drush config:export -y
```
We'll use this export on the production site.

If you add new modules or config, these should also be added to the recipe.
If you're just modifying some value use the "simpleConfigUpdate" action in the recipe.yml.
If you're creating entirely new config that a contrib module does not provide by
default, copy the config export to the recipe, and then remove the UUID's and core hashes. 

## Local Hostname Setup
--------------------
To make sure https://local.iamhere.social opens your local development site (and not the live one), you need to add a line to your system's hosts file.

Step 1: Edit your hosts file and add this line to the bottom of the file:
```
127.0.0.1 local.iamhere.social
```

Hosts file location:
- macOS/Linux: /etc/hosts
- Windows: C:\Windows\System32\drivers\etc\hosts

You may need administrative privileges to edit this file.

Step 2: Save and test
- Save the file
- Restart your browser or flush your DNS cache
- Visit https://local.iamhere.social

## Accessing the Site
------------------
- Local URL: https://local.iamhere.social
- Admin login: Use the credentials you set during install (e.g. admin / admin)
All commands should be run inside the container:
ddev ssh

## Useful DDEV Commands
--------------------
```shell
ddev start # Start DDEV environment
ddev stop # Stop the project
ddev restart # Restart containers
ddev describe # Show project info and URLs
ddev ssh # SSH into the container
ddev drush # Run Drush commands inside the container
```

## Cleanup
-------
To remove the local project and its database:
```shell
ddev stop --remove-data
```

## PII
Create a key so you can save PII field data.
```shell
openssl rand -base64 16 > keys/pii.key
```

## Need Help?
----------

If you're having trouble:

- Make sure Docker and DDEV are running
- Check that your /etc/hosts entry is correct
- Use `ddev describe` to verify site info
- Try restarting your browser after changes

For more guidance, visit the DDEV Documentation: https://ddev.readthedocs.io/
