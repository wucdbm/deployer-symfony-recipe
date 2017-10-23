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