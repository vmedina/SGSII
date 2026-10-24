param(
    [string]$XamppPath = ''
)

$ErrorActionPreference = 'Stop'
$AppName = 'sgsii'

function Test-TcpPort([int]$Port) {
    $client = New-Object Net.Sockets.TcpClient
    try {
        $connection = $client.ConnectAsync('127.0.0.1', $Port)
        if (-not $connection.Wait(700)) { return $false }
        return $client.Connected
    } catch {
        return $false
    } finally {
        $client.Dispose()
    }
}

function Start-XamppComponent([string]$Name, [string]$BatchFile, [int]$Port) {
    if (Test-TcpPort $Port) {
        Write-Host "$Name ya está iniciado."
        return
    }
    if (-not (Test-Path -LiteralPath $BatchFile)) {
        throw "No se encontró el iniciador de $Name en $BatchFile"
    }
    Write-Host "Iniciando $Name..."
    Start-Process -FilePath $env:ComSpec `
        -ArgumentList @('/c', "`"$BatchFile`"") `
        -WorkingDirectory $XamppPath `
        -WindowStyle Hidden | Out-Null
    for ($attempt = 0; $attempt -lt 20; $attempt++) {
        Start-Sleep -Seconds 1
        if (Test-TcpPort $Port) {
            Write-Host "$Name iniciado correctamente."
            return
        }
    }
    throw "$Name no pudo iniciar en el puerto $Port. Revise XAMPP Control Panel y los conflictos de puerto."
}

if ([string]::IsNullOrWhiteSpace($XamppPath)) {
    $XamppPath = Read-Host 'Ruta donde está instalado XAMPP [C:\xampp]'
    if ([string]::IsNullOrWhiteSpace($XamppPath)) { $XamppPath = 'C:\xampp' }
}
$XamppPath = [IO.Path]::GetFullPath($XamppPath.Trim().TrimEnd('\'))

$sourceRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$phpExe = Join-Path $XamppPath 'php\php.exe'
$htdocs = Join-Path $XamppPath 'htdocs'
$installRoot = Join-Path $htdocs $AppName

if (-not (Test-Path -LiteralPath $phpExe)) {
    throw "No se encontró PHP de XAMPP en $phpExe"
}
if (-not (Test-Path -LiteralPath $htdocs)) {
    throw "No se encontró htdocs en $htdocs"
}

$phpModules = @(& $phpExe -m)
if ($LASTEXITCODE -ne 0 -or $phpModules -notcontains 'pdo_mysql') {
    throw "XAMPP no tiene pdo_mysql habilitado. Revise $XamppPath\php\php.ini"
}

if ([IO.Path]::GetFullPath($sourceRoot) -ne [IO.Path]::GetFullPath($installRoot)) {
    if (Test-Path -LiteralPath $installRoot) {
        $existingItem = Get-ChildItem -LiteralPath $installRoot -Force | Select-Object -First 1
        if ($null -ne $existingItem) {
            $existingApp = (Test-Path -LiteralPath (Join-Path $installRoot 'public\index.php')) `
                -and (Test-Path -LiteralPath (Join-Path $installRoot 'scripts\install_mysql.php'))
            if (-not $existingApp) {
                throw "La carpeta destino no está vacía y no parece SGSII: $installRoot"
            }
            Write-Host "Actualizando la instalación existente: $installRoot"
        }
    } else {
        New-Item -ItemType Directory -Path $installRoot | Out-Null
    }

    foreach ($directory in @('public', 'src', 'scripts', 'tests', 'docs')) {
        $targetDirectory = Join-Path $installRoot $directory
        New-Item -ItemType Directory -Path $targetDirectory -Force | Out-Null
        Get-ChildItem -LiteralPath (Join-Path $sourceRoot $directory) -Force |
            Copy-Item -Destination $targetDirectory -Recurse -Force
    }

    $databaseTarget = Join-Path $installRoot 'database'
    New-Item -ItemType Directory -Path $databaseTarget -Force | Out-Null
    foreach ($schema in @('schema.sql', 'schema.mysql.sql')) {
        Copy-Item -LiteralPath (Join-Path $sourceRoot "database\$schema") -Destination $databaseTarget -Force
    }
    foreach ($file in @('README.md', 'config.example.php', 'index.php', '.htaccess')) {
        Copy-Item -LiteralPath (Join-Path $sourceRoot $file) -Destination $installRoot -Force
    }
}

Write-Host "Archivos sincronizados en: $installRoot"

Start-XamppComponent 'MySQL' (Join-Path $XamppPath 'mysql_start.bat') 3306

Push-Location $installRoot
try {
    & $phpExe scripts\install_mysql.php
    if ($LASTEXITCODE -ne 0) { throw 'El instalador MySQL informó un error.' }
    & $phpExe scripts\check_install.php
    if ($LASTEXITCODE -ne 0) { throw 'La verificación de MySQL informó un error.' }
    if ($phpModules -contains 'pdo_sqlite' -and $phpModules -contains 'sqlite3') {
        & $phpExe tests\run.php
        if ($LASTEXITCODE -ne 0) { throw 'La suite automatizada informó un error.' }
    }
} finally {
    Pop-Location
}

Start-XamppComponent 'Apache' (Join-Path $XamppPath 'apache_start.bat') 80

$url = "http://localhost/$AppName/"
$response = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 8
if ($response.StatusCode -ne 200 -or $response.Content -notmatch '<title>SGSII</title>') {
    throw "Apache respondió, pero no se reconoció SGSII en $url"
}

Write-Host ''
Write-Host 'INSTALACIÓN/ACTUALIZACIÓN COMPLETADA'
Write-Host "Abra: $url"
Write-Host 'Para cambios de aspecto, edite public\assets y vuelva a ejecutar este mismo script.'
Write-Host 'No es necesario reiniciar Apache; use Ctrl+F5 en el navegador para renovar CSS y JavaScript.'
