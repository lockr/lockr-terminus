# Terminus Lockr Plugin

A plugin for Terminus-CLI that provides a one-command method for
installing Lockr and ensuring all available modules/plugins are
using proper API and encryption key management.

## Installation

```
mkdir -p ~/.terminus/plugins && cd ~/.terminus/plugins
git clone https://github.com/lockr/lockr-terminus
```

## Usage

Use the "lockdown" command to install Lockr on your site and patch
modules/plugins.

Note that the target site must be in SFTP mode.

```
terminus lockdown --site=sitename alice@example.com
```

If you have already signed up with Lockr, a login password is required
to authenticate with our backend.

```
terminus lockdown --site=sitename --password='alicepassword' alice@example.com
```

