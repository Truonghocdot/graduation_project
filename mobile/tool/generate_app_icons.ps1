param()

$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.Drawing

$mobileRoot = Split-Path -Parent $PSScriptRoot
$pngFormat = [System.Drawing.Imaging.ImageFormat]::Png
$pixelFormat = [System.Drawing.Imaging.PixelFormat]::Format32bppArgb

function New-IconBitmap {
    param(
        [ValidateSet('client', 'driver')]
        [string]$Variant,
        [switch]$Transparent
    )

    $size = 1024
    $bitmap = [System.Drawing.Bitmap]::new($size, $size, $pixelFormat)
    $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
    $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $graphics.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality

    try {
        $graphics.Clear([System.Drawing.Color]::Transparent)

        if (-not $Transparent) {
            if ($Variant -eq 'client') {
                $startColor = [System.Drawing.ColorTranslator]::FromHtml('#146B52')
                $endColor = [System.Drawing.ColorTranslator]::FromHtml('#0B4135')
            } else {
                $startColor = [System.Drawing.ColorTranslator]::FromHtml('#215F9A')
                $endColor = [System.Drawing.ColorTranslator]::FromHtml('#153D65')
            }

            $background = [System.Drawing.Drawing2D.LinearGradientBrush]::new(
                [System.Drawing.Point]::new(100, 70),
                [System.Drawing.Point]::new(930, 960),
                $startColor,
                $endColor
            )
            try {
                $graphics.FillRectangle($background, 0, 0, $size, $size)
            } finally {
                $background.Dispose()
            }

            $shade = [System.Drawing.SolidBrush]::new(
                [System.Drawing.Color]::FromArgb(24, 0, 0, 0)
            )
            try {
                $graphics.FillPolygon($shade, [System.Drawing.Point[]]@(
                    [System.Drawing.Point]::new(0, 770),
                    [System.Drawing.Point]::new(1024, 500),
                    [System.Drawing.Point]::new(1024, 1024),
                    [System.Drawing.Point]::new(0, 1024)
                ))
            } finally {
                $shade.Dispose()
            }
        }

        if ($Variant -eq 'client') {
            Draw-ClientMark -Graphics $graphics
        } else {
            Draw-DriverMark -Graphics $graphics
        }
    } finally {
        $graphics.Dispose()
    }

    return $bitmap
}

function New-PinPath {
    param([int]$OffsetX, [int]$OffsetY)

    $path = [System.Drawing.Drawing2D.GraphicsPath]::new()
    $path.StartFigure()
    $path.AddBezier(
        512 + $OffsetX, 162 + $OffsetY,
        348 + $OffsetX, 162 + $OffsetY,
        252 + $OffsetX, 282 + $OffsetY,
        252 + $OffsetX, 426 + $OffsetY
    )
    $path.AddBezier(
        252 + $OffsetX, 426 + $OffsetY,
        252 + $OffsetX, 610 + $OffsetY,
        429 + $OffsetX, 770 + $OffsetY,
        512 + $OffsetX, 846 + $OffsetY
    )
    $path.AddBezier(
        512 + $OffsetX, 846 + $OffsetY,
        595 + $OffsetX, 770 + $OffsetY,
        772 + $OffsetX, 610 + $OffsetY,
        772 + $OffsetX, 426 + $OffsetY
    )
    $path.AddBezier(
        772 + $OffsetX, 426 + $OffsetY,
        772 + $OffsetX, 282 + $OffsetY,
        676 + $OffsetX, 162 + $OffsetY,
        512 + $OffsetX, 162 + $OffsetY
    )
    $path.CloseFigure()
    return $path
}

function Draw-ClientMark {
    param([System.Drawing.Graphics]$Graphics)

    $shadowPath = New-PinPath -OffsetX 18 -OffsetY 24
    $shadowBrush = [System.Drawing.SolidBrush]::new(
        [System.Drawing.Color]::FromArgb(48, 0, 0, 0)
    )
    try {
        $Graphics.FillPath($shadowBrush, $shadowPath)
    } finally {
        $shadowBrush.Dispose()
        $shadowPath.Dispose()
    }

    $pinPath = New-PinPath -OffsetX 0 -OffsetY 0
    $whiteBrush = [System.Drawing.SolidBrush]::new(
        [System.Drawing.Color]::FromArgb(255, 255, 255, 255)
    )
    try {
        $Graphics.FillPath($whiteBrush, $pinPath)
    } finally {
        $pinPath.Dispose()
    }

    $accentBrush = [System.Drawing.SolidBrush]::new(
        [System.Drawing.ColorTranslator]::FromHtml('#B35C21')
    )
    try {
        $Graphics.FillEllipse($accentBrush, 366, 280, 292, 292)
    } finally {
        $accentBrush.Dispose()
        $whiteBrush.Dispose()
    }
}

function Draw-DriverMark {
    param([System.Drawing.Graphics]$Graphics)

    $shadowPen = [System.Drawing.Pen]::new(
        [System.Drawing.Color]::FromArgb(48, 0, 0, 0),
        96
    )
    $shadowPen.StartCap = [System.Drawing.Drawing2D.LineCap]::Round
    $shadowPen.EndCap = [System.Drawing.Drawing2D.LineCap]::Round

    $whitePen = [System.Drawing.Pen]::new(
        [System.Drawing.Color]::FromArgb(255, 255, 255, 255),
        96
    )
    $whitePen.StartCap = [System.Drawing.Drawing2D.LineCap]::Round
    $whitePen.EndCap = [System.Drawing.Drawing2D.LineCap]::Round

    try {
        $Graphics.DrawEllipse($shadowPen, 210, 222, 622, 622)
        $Graphics.DrawLine($shadowPen, 530, 533, 530, 330)
        $Graphics.DrawLine($shadowPen, 530, 533, 358, 676)
        $Graphics.DrawLine($shadowPen, 530, 533, 702, 676)

        $Graphics.DrawEllipse($whitePen, 192, 198, 622, 622)
        $Graphics.DrawLine($whitePen, 512, 509, 512, 306)
        $Graphics.DrawLine($whitePen, 512, 509, 340, 652)
        $Graphics.DrawLine($whitePen, 512, 509, 684, 652)
    } finally {
        $shadowPen.Dispose()
        $whitePen.Dispose()
    }

    $accentBrush = [System.Drawing.SolidBrush]::new(
        [System.Drawing.ColorTranslator]::FromHtml('#B35C21')
    )
    try {
        $Graphics.FillEllipse($accentBrush, 435, 432, 154, 154)
    } finally {
        $accentBrush.Dispose()
    }
}

function New-ResizedBitmap {
    param(
        [System.Drawing.Bitmap]$Source,
        [int]$Size,
        [switch]$Transparent
    )

    $targetPixelFormat = if ($Transparent) {
        $pixelFormat
    } else {
        [System.Drawing.Imaging.PixelFormat]::Format24bppRgb
    }
    $bitmap = [System.Drawing.Bitmap]::new($Size, $Size, $targetPixelFormat)
    $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
    try {
        if ($Transparent) {
            $graphics.Clear([System.Drawing.Color]::Transparent)
        } else {
            $graphics.Clear([System.Drawing.Color]::White)
        }
        $graphics.CompositingMode = [System.Drawing.Drawing2D.CompositingMode]::SourceCopy
        $graphics.CompositingQuality = [System.Drawing.Drawing2D.CompositingQuality]::HighQuality
        $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
        $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
        $graphics.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
        $graphics.DrawImage(
            $Source,
            [System.Drawing.Rectangle]::new(0, 0, $Size, $Size),
            0,
            0,
            $Source.Width,
            $Source.Height,
            [System.Drawing.GraphicsUnit]::Pixel
        )
    } finally {
        $graphics.Dispose()
    }
    return $bitmap
}

function Save-IconPng {
    param(
        [System.Drawing.Bitmap]$Source,
        [int]$Size,
        [string]$Path,
        [switch]$Transparent
    )

    $directory = Split-Path -Parent $Path
    [System.IO.Directory]::CreateDirectory($directory) | Out-Null
    $bitmap = New-ResizedBitmap -Source $Source -Size $Size -Transparent:$Transparent
    try {
        $bitmap.Save($Path, $pngFormat)
    } finally {
        $bitmap.Dispose()
    }
}

function Get-PngBytes {
    param(
        [System.Drawing.Bitmap]$Source,
        [int]$Size
    )

    $bitmap = New-ResizedBitmap -Source $Source -Size $Size
    $stream = [System.IO.MemoryStream]::new()
    try {
        $bitmap.Save($stream, $pngFormat)
        return $stream.ToArray()
    } finally {
        $stream.Dispose()
        $bitmap.Dispose()
    }
}

function Save-WindowsIcon {
    param(
        [System.Drawing.Bitmap]$Source,
        [string]$Path
    )

    $sizes = @(16, 32, 48, 256)
    $images = @($sizes | ForEach-Object {
        [PSCustomObject]@{
            Size = $_
            Bytes = Get-PngBytes -Source $Source -Size $_
        }
    })

    $directory = Split-Path -Parent $Path
    [System.IO.Directory]::CreateDirectory($directory) | Out-Null
    $stream = [System.IO.File]::Open($Path, [System.IO.FileMode]::Create)
    $writer = [System.IO.BinaryWriter]::new($stream)
    try {
        $writer.Write([uint16]0)
        $writer.Write([uint16]1)
        $writer.Write([uint16]$images.Count)

        $offset = 6 + (16 * $images.Count)
        foreach ($image in $images) {
            $edge = if ($image.Size -eq 256) { 0 } else { $image.Size }
            $writer.Write([byte]$edge)
            $writer.Write([byte]$edge)
            $writer.Write([byte]0)
            $writer.Write([byte]0)
            $writer.Write([uint16]1)
            $writer.Write([uint16]32)
            $writer.Write([uint32]$image.Bytes.Length)
            $writer.Write([uint32]$offset)
            $offset += $image.Bytes.Length
        }

        foreach ($image in $images) {
            $writer.Write([byte[]]$image.Bytes)
        }
    } finally {
        $writer.Dispose()
        $stream.Dispose()
    }
}

function Export-AppleIconSet {
    param(
        [System.Drawing.Bitmap]$Source,
        [string]$IconSetPath
    )

    $contentsPath = Join-Path $IconSetPath 'Contents.json'
    $contents = Get-Content -Raw $contentsPath | ConvertFrom-Json
    foreach ($image in $contents.images) {
        if (-not $image.filename) {
            continue
        }

        $points = [double]($image.size -split 'x')[0]
        $scale = [int]($image.scale.TrimEnd('x'))
        $pixels = [int][Math]::Round($points * $scale)
        Save-IconPng -Source $Source -Size $pixels -Path (Join-Path $IconSetPath $image.filename)
    }
}

function Export-AppIcons {
    param(
        [ValidateSet('client', 'driver')]
        [string]$App
    )

    $appRoot = Join-Path $mobileRoot $App
    $master = New-IconBitmap -Variant $App
    $foreground = New-IconBitmap -Variant $App -Transparent

    try {
        Save-IconPng -Source $master -Size 1024 -Path (
            Join-Path $appRoot 'assets/branding/app_icon.png'
        )

        $androidLegacySizes = [ordered]@{
            'mdpi' = 48
            'hdpi' = 72
            'xhdpi' = 96
            'xxhdpi' = 144
            'xxxhdpi' = 192
        }
        $androidForegroundSizes = [ordered]@{
            'mdpi' = 108
            'hdpi' = 162
            'xhdpi' = 216
            'xxhdpi' = 324
            'xxxhdpi' = 432
        }

        foreach ($density in $androidLegacySizes.Keys) {
            $mipmapPath = Join-Path $appRoot "android/app/src/main/res/mipmap-$density"
            Save-IconPng -Source $master -Size $androidLegacySizes[$density] -Path (
                Join-Path $mipmapPath 'ic_launcher.png'
            )
            Save-IconPng -Source $master -Size $androidLegacySizes[$density] -Path (
                Join-Path $mipmapPath 'ic_launcher_round.png'
            )
        }

        foreach ($density in $androidForegroundSizes.Keys) {
            Save-IconPng -Source $foreground -Size $androidForegroundSizes[$density] -Path (
                Join-Path $appRoot "android/app/src/main/res/drawable-$density/ic_launcher_foreground.png"
            ) -Transparent
        }

        Export-AppleIconSet -Source $master -IconSetPath (
            Join-Path $appRoot 'ios/Runner/Assets.xcassets/AppIcon.appiconset'
        )
        Export-AppleIconSet -Source $master -IconSetPath (
            Join-Path $appRoot 'macos/Runner/Assets.xcassets/AppIcon.appiconset'
        )

        Save-IconPng -Source $master -Size 192 -Path (Join-Path $appRoot 'web/icons/Icon-192.png')
        Save-IconPng -Source $master -Size 512 -Path (Join-Path $appRoot 'web/icons/Icon-512.png')
        Save-IconPng -Source $master -Size 192 -Path (Join-Path $appRoot 'web/icons/Icon-maskable-192.png')
        Save-IconPng -Source $master -Size 512 -Path (Join-Path $appRoot 'web/icons/Icon-maskable-512.png')
        Save-IconPng -Source $master -Size 32 -Path (Join-Path $appRoot 'web/favicon.png')

        Save-WindowsIcon -Source $master -Path (
            Join-Path $appRoot 'windows/runner/resources/app_icon.ico'
        )
    } finally {
        $foreground.Dispose()
        $master.Dispose()
    }
}

Export-AppIcons -App 'client'
Export-AppIcons -App 'driver'

Write-Host 'Generated client and driver app icons.'
