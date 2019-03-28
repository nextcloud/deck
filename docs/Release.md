Releasing a new version works quite easy with [krankerl](https://github.com/ChristophWurst/krankerl) and [github-release](https://github.com/aktau/github-release) installed:

1. Run krankerl to build the package

```
krankerl package
```

2. Tag the release on GitHub

```
# For a prerelease
github-release release -u nextcloud -r deck -t v0.3.1 -p

# For a regular release
github-release release -u nextcloud -r deck -t v0.3.1
```

3. Upload the release package to GitHub

```
github-release upload -u nextcloud -r deck -t v0.3.1 -n deck.tar.gz -f build/artifacts/deck.tar.gz
```

4. Run krankerl to release the package to the app store (add `--nightly` for prerelease packages)

```
krankerl publish https://github.com/nextcloud/deck/releases/download/v0.3.1/deck.tar.gz  
```

## Release PR template

```
## Backports

- [ ] ...

## Translations

- [ ] ...

## Release

- [ ] Set proper Nextcloud versions in info.xml
- [ ] Update changelog
- [ ] Build test release
- [ ] Tested on 
  - [ ] Nextcloud 13
  - [ ] Nextcloud 14
  - [ ] Nextcloud 15
- [ ] Merge
- [ ] Build final release
- [ ] Publish release
- [ ] Upload to the app store
```
