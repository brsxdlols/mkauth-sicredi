param(
  [Parameter(Mandatory=$true)][string]$HostName,
  [int]$Port = 22,
  [string]$User = 'root',
  [Parameter(Mandatory=$true)][string]$Password,
  [string]$HostKey = '',
  [string]$RemoteDir = '/tmp/mkauth-sicredi-install'
)

$ErrorActionPreference = 'Stop'
$repo = Split-Path -Parent $PSScriptRoot
$target = "${User}@${HostName}:$RemoteDir"

$hostKeyArgs = @()
if ($HostKey) { $hostKeyArgs = @('-hostkey', $HostKey) }

Write-Host "Enviando arquivos para $target"
plink -ssh $User@$HostName -P $Port -pw $Password @hostKeyArgs -batch "rm -rf '$RemoteDir' && mkdir -p '$RemoteDir'"
pscp -r -P $Port -pw $Password @hostKeyArgs "$repo\*" $target
Write-Host "Executando install.sh"
plink -ssh $User@$HostName -P $Port -pw $Password @hostKeyArgs -batch "cd '$RemoteDir' && bash install.sh"
