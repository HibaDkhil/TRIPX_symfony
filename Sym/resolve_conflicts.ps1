$file = Join-Path (Get-Location) 'templates\front\users.html.twig'
$content = [System.IO.File]::ReadAllText($file, [System.Text.Encoding]::UTF8)

$pattern = '<<<<<<< HEAD[\s\S]*?=======\r?\n([\s\S]*?)>>>>>>> origin/main\r?\n'
$result = [regex]::Replace($content, $pattern, { param($m) $m.Groups[1].Value })

[System.IO.File]::WriteAllText($file, $result, [System.Text.Encoding]::UTF8)

$remaining = ([regex]::Matches($result, '<<<<<<<')).Count
Write-Host "Done. Remaining conflict markers: $remaining"
