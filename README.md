# Terminus Lockr Plugin

A plugin for Terminus-CLI that provides a one-command method for
installing Lockr and ensuring all available modules/plugins are
using proper API and encryption key management.

## Installation

Via Composer (recommended):

```
composer create-project -d ~/.terminus/plugins/ lockr/lockr-terminus:~1
```

Via Git:

```
mkdir -p ~/.terminus/plugins && cd ~/.terminus/plugins
git clone https://github.com/lockr/lockr-terminus
```

## Usage

Use the "lockdown" command to install Lockr on your site and patch
modules/plugins.

```
terminus lockdown sitename alice@example.com
```

If you have already signed up with Lockr, a login password is required
to authenticate with our backend.

```
terminus lockdown sitename alice@example.com 'alicepassword'
```

