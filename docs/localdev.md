# Local Development Setup: Drupal CMS + DDEV

This guide explains how to set up the Drupal CMS project locally using DDEV for containerized
development.

## Prerequisites
-------------

Before getting started, make sure you have the following installed:
- Docker: https://docs.docker.com/get-docker/
- DDEV: https://ddev.readthedocs.io/en/stable/#installation

On macOS, you can install both with Homebrew:
```
brew install ddev
```

## Set Up the Local Site
---------------------

1. Clone the repository:
```
git clone <your-repo-url> iamhere
cd iamhere
```
2. Start the DDEV environment, this will build the containers and configure the local project:
```
ddev start
```
3. Add instructions to setup a local. This will likely be a site-install and a recipe, but still @todo.

we will need to create a process for this, it's not created yet.

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
```
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
```
ddev stop --remove-data
```

## Need Help?
----------

If you're having trouble:

- Make sure Docker and DDEV are running
- Check that your /etc/hosts entry is correct
- Use `ddev describe` to verify site info
- Try restarting your browser after changes

For more guidance, visit the DDEV Documentation: https://ddev.readthedocs.io/
