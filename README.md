# Release Manager
Simple Release Manager. This will manage your releases and will switch over a symlink once a release is ready to be active. This allows you to make releases without downtime on your webspace.

The reason to choose this over other tools is that it only depends on minimal tools available on the server it's ran on (unix, `php` and `tar` being available). This means it's an ideal candidate for shared hosting which has SSH access. These deployment targets normally have quite harsh restrictions placed on it which might make other tools not usable.

## Installation
Download the PHAR from the releases.

### Prerequisites
* Unix filesystem
* PHP
* Tar CLI util
* Bash (for running post install script)

## Usage
### init
Sets up the target directory for the releases. This will create some mandatory directories and ensure you are all setup for creating releases.
```bash
php release.phar init /var/www/html/
```
This will create the following folder structure:
```
releases/        # Folder where all releases will be stored
shared/          # Can be used for shared data between releases
.lock            # Will be used for locking and checking what the current release is
post_install.sh  # (Optional) Will be run after each release, but before switching symlinks
```

After the first release, there will also be a `current` symlink to the current release folder. 
> **Tip**: use the `current` symlink (or a subfolder thereof) as your website's root folder, then any new releases will automatically be visible.

### release
Creates a release from an archive. Note: currently only supports `.tar.gz` files.
```bash
php release.phar release -r "v1.2.3" /var/www/html/ release.tar.gz
```
This will create a new release, extracts the archive to that folder, runs the `post_install.sh` script and then switches over the current release to the one created. 

If anything fails during the release, the release is kept for analysis, but the symlink is never switched over. Use the `unlock` command to clean it up so you can deploy again.

### rollback
Rolls back to an older release. By default, the previous one.
```bash
php release.phar rollback /var/www/html/
```
This will switch over the `current` symlink to release and allows you to roll back to a previous release.

Optionally, you can also pass a specific release if you which to switch over to a specific release. This way you can also switch to a newer one if needed. 
> **Note**: this is a release number, and corresponds with a specific release directory.
```bash
php release.phar rollback -r 5 /var/www/html/
```

### unlock
Unlocks and cleans up any failed release. If a release has failed, it's not automatically cleaned up. This ensures you can debug why the release failed.
```bash
php release.phar unlock /var/www/html/
```

### cleanup
Cleans up old releases. By default, it keeps 3 releases (including the current one).
```bash
php release.phar cleanup /var/www/html/
```

Optionally, pass how many releases to keep (including the current one).
```bash
php release.phar cleanup -k 5 /var/www/html/
```

## Github actions
Simple example how it easily integrates into a deployment action for your application or website.

```yaml
# .github/workflows/deploy.yml
name: Deploy

on: workflow_dispatch

jobs:
    deploy:
        name: Deploy
        runs-on: ubuntu-22.04

        steps:
            -   name: Checkout
                uses: actions/checkout@v3
                
            # Build your application and prepare it to be added to the release archive

            -   name: Archive code
                run: tar -czf release.tar.gz ./

            -   name: Upload archive
                uses: appleboy/scp-action@v1
                with:
                    host: ${{ secrets.SSH_HOST }}
                    username: ${{ secrets.SSH_USERNAME }}
                    key: ${{ secrets.SSH_PRIVATE_KEY }}
                    source: "release.tar.gz"
                    target: "/home/user"

            -   name: Deploy archive
                uses: appleboy/ssh-action@v1
                with:
                    host: ${{ secrets.SSH_HOST }}
                    username: ${{ secrets.SSH_USERNAME }}
                    key: ${{ secrets.SSH_PRIVATE_KEY }}
                    script: "php deploy.phar deploy /var/www/html release.tar.gz"
```

```yaml
# .github/workflows/unlock.yml
name: Unlock

on: workflow_dispatch

jobs:
    unlock:
        name: Unlock
        runs-on: ubuntu-22.04

        steps:
            -   name: Unlocking release
                uses: appleboy/ssh-action@v1
                with:
                    host: ${{ secrets.SSH_HOST }}
                    username: ${{ secrets.SSH_USERNAME }}
                    key: ${{ secrets.SSH_PRIVATE_KEY }}
                    script: "php deploy.phar unlock /var/www/html"
```

```yaml
# .github/workflows/rollback.yml
name: Rollback

on: workflow_dispatch

jobs:
    rollback:
        name: Rollback
        runs-on: ubuntu-22.04

        steps:
            -   name: Rollback to previous release
                uses: appleboy/ssh-action@v1
                with:
                    host: ${{ secrets.SSH_HOST }}
                    username: ${{ secrets.SSH_USERNAME }}
                    key: ${{ secrets.SSH_PRIVATE_KEY }}
                    script: "php deploy.phar rollback /var/www/html"

```
