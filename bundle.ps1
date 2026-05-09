$files = @(
    "composer.json",
    "package.json",
    "routes/web.php",
    "routes/api.php",
    "app/Ai/Agents/PostContentGenerator.php",
    "app/Ai/Agents/BrandAnalyzer.php",
    "app/Ai/Agents/PostContentReviewer.php",
    "app/Ai/Agents/PostContentStreamer.php",
    "resources/js/lib/firebase.ts",
    "config/ai.php",
    "config/postpro.php",
    "Dockerfile",
    "cloudbuild.yaml"
)

$outputFile = "postpro_context_for_ai_studio.txt"
"" | Out-File -FilePath $outputFile -Encoding utf8

foreach ($f in $files) {
    if (Test-Path $f) {
        "--- FILE: $f ---" | Out-File -FilePath $outputFile -Append -Encoding utf8
        Get-Content $f -Raw | Out-File -FilePath $outputFile -Append -Encoding utf8
        "`n" | Out-File -FilePath $outputFile -Append -Encoding utf8
    }
}
