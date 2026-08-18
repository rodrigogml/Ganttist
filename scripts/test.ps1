$ErrorActionPreference = 'Stop'
$phpArgs = @('-d','extension=mbstring','-d','extension=openssl','-d','extension=fileinfo','-d','xdebug.mode=off')
& php @phpArgs 'vendor/phpunit/phpunit/phpunit'
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
& php @phpArgs 'vendor/bin/pint' '--test'
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
$nodeDir = (Resolve-Path '.tools/node-v22.14.0-win-x64').Path
$env:PATH = $nodeDir + ';' + $env:PATH
& npm test
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
& npx vue-tsc --noEmit
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
& npm run build
exit $LASTEXITCODE
