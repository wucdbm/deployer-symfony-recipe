# Usage

See deploy.sample.php, copy it to your project, modify & enjoy

You should always add your `symfony_env`, and optionally set the `type` to `tag`

```yaml
someServer:
  type: (branch|tag)
  symfony_env: test
```

# Versions

Versions always follow `deployer/deployer` and are compatible with the respective major.minor version.

# Opcache Clear

For cgi-fcgi (used by the opcache clear script) to work, the below package needs to be installed on Ubuntu-based systems

```
apt-get install libfcgi0ldbl
```

# TODOs

- Slack or System message upon failure