<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Gestion des erreurs
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée');
    }

    if (!isset($_FILES['excelFile']) || !isset($_POST['siteUrl'])) {
        throw new Exception('Fichier Excel ou URL du site manquant');
    }

    $uploadedFile = $_FILES['excelFile'];
    $siteUrl = filter_var($_POST['siteUrl'], FILTER_VALIDATE_URL);

    if (!$siteUrl) {
        throw new Exception('URL du site invalide');
    }

    if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Erreur lors de l\'upload du fichier');
    }

    // Vérifier l'extension du fichier
    $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExtension, ['xlsx', 'xls'])) {
        throw new Exception('Format de fichier non supporté. Utilisez .xlsx ou .xls');
    }

    // Créer le dossier uploads s'il n'existe pas
    $uploadsDir = __DIR__ . '/uploads';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }

    // Déplacer le fichier uploadé
    $fileName = 'excel_' . time() . '.' . $fileExtension;
    $filePath = $uploadsDir . '/' . $fileName;

    if (!move_uploaded_file($uploadedFile['tmp_name'], $filePath)) {
        throw new Exception('Impossible de sauvegarder le fichier');
    }

    // Traiter le fichier Excel
    $result = processExcelFile($filePath, $siteUrl);

    echo json_encode([
        'success' => true,
        'message' => $result['message'],
        'downloadUrl' => $result['downloadUrl'],
        'processed' => $result['processed']
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function processExcelFile($filePath, $siteUrl) {
    require_once 'vendor/autoload.php';

    // Charger le fichier Excel
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
    $worksheet = $spreadsheet->getActiveSheet();

    // Trouver les colonnes "Adresse" et "Nouvelle Adresse"
    $headers = [];
    $adresseCol = null;
    $nouvelleAdresseCol = null;

    foreach ($worksheet->getRowIterator(1, 1) as $row) {
        foreach ($row->getCellIterator() as $cell) {
            $value = trim($cell->getValue());
            $headers[$cell->getColumn()] = $value;

            if (stripos($value, 'adresse') !== false && stripos($value, 'nouvelle') === false) {
                $adresseCol = $cell->getColumn();
            } elseif (stripos($value, 'nouvelle') !== false && stripos($value, 'adresse') !== false) {
                $nouvelleAdresseCol = $cell->getColumn();
            }
        }
    }

    if (!$adresseCol) {
        throw new Exception('Colonne "Adresse" non trouvée dans le fichier Excel');
    }

    if (!$nouvelleAdresseCol) {
        throw new Exception('Colonne "Nouvelle Adresse" non trouvée dans le fichier Excel');
    }

    // Étape 1: Extraire le sitemap du site
    $sitemapUrls = extractSitemap($siteUrl);

    // Étape 2: Préparer le cache des pages pour le crawling (si nécessaire)
    $crawledPages = null;

    $processedCount = 0;
    $redirectedCount = 0;
    $notFoundCount = 0;
    $totalRows = $worksheet->getHighestRow();

    // Traiter chaque ligne (en commençant à la ligne 2 pour ignorer les en-têtes)
    for ($row = 2; $row <= $totalRows; $row++) {
        $adresseCell = $worksheet->getCell($adresseCol . $row);
        $adresseValue = trim($adresseCell->getValue());

        if (empty($adresseValue)) {
            continue;
        }

        // Étape 3: Tester si l'URL redirige correctement
        $urlStatus = checkUrlStatus($adresseValue);

        if ($urlStatus['status'] === 200) {
            // L'URL fonctionne, on garde l'URL finale (après redirections)
            $worksheet->getCell($nouvelleAdresseCol . $row)->setValue($urlStatus['final_url']);
            $redirectedCount++;
            $processedCount++;
        } else {
            // URL en erreur (404, etc.), on cherche le contenu correspondant

            // Crawler le site seulement si pas encore fait
            if ($crawledPages === null) {
                $crawledPages = crawlSiteFromSitemap($siteUrl, $sitemapUrls);
            }

            // Chercher la correspondance dans les pages crawlées
            $bestMatch = findBestMatchForUrl($adresseValue, $crawledPages);

            if ($bestMatch) {
                $worksheet->getCell($nouvelleAdresseCol . $row)->setValue($bestMatch);
                $notFoundCount++;
                $processedCount++;
            }
        }
    }

    // Sauvegarder le fichier mis à jour
    $outputFileName = 'updated_' . time() . '.xlsx';
    $outputPath = __DIR__ . '/uploads/' . $outputFileName;

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($outputPath);

    return [
        'message' => "Traitement terminé ! $processedCount URL(s) mises à jour sur " . ($totalRows - 1) . " lignes. ($redirectedCount redirections OK, $notFoundCount correspondances trouvées)",
        'downloadUrl' => 'uploads/' . $outputFileName,
        'processed' => $processedCount
    ];
}

function extractSitemap($siteUrl) {
    $sitemapUrls = [];

    // Tentative de récupération du sitemap principal
    $sitemapUrl = rtrim($siteUrl, '/') . '/sitemap.xml';
    $sitemapContent = fetchPageContent($sitemapUrl);

    if (!$sitemapContent) {
        // Essayer robots.txt pour trouver le sitemap
        $robotsUrl = rtrim($siteUrl, '/') . '/robots.txt';
        $robotsContent = fetchPageContent($robotsUrl);

        if ($robotsContent && preg_match('/Sitemap:\s*(.+)/i', $robotsContent, $matches)) {
            $sitemapUrl = trim($matches[1]);
            $sitemapContent = fetchPageContent($sitemapUrl);
        }
    }

    if ($sitemapContent) {
        $sitemapUrls = parseSitemap($sitemapContent, $siteUrl);
    }

    // Si pas de sitemap trouvé, on utilise une liste de base
    if (empty($sitemapUrls)) {
        $sitemapUrls = [$siteUrl]; // Au minimum la page d'accueil
    }

    return $sitemapUrls;
}

function parseSitemap($xmlContent, $baseUrl) {
    $urls = [];

    // Nettoyer le XML
    $xmlContent = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $xmlContent);

    // Parser le XML
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlContent);

    if ($xml === false) {
        return [];
    }

    // Traiter sitemap index (contient d'autres sitemaps)
    if (isset($xml->sitemap)) {
        foreach ($xml->sitemap as $sitemap) {
            if (isset($sitemap->loc)) {
                $subSitemapContent = fetchPageContent((string)$sitemap->loc);
                if ($subSitemapContent) {
                    $subUrls = parseSitemap($subSitemapContent, $baseUrl);
                    $urls = array_merge($urls, $subUrls);
                }
            }
        }
    }

    // Traiter sitemap URL (contient les URLs finales)
    if (isset($xml->url)) {
        foreach ($xml->url as $url) {
            if (isset($url->loc)) {
                $urls[] = (string)$url->loc;
            }
        }
    }

    return array_unique($urls);
}

function checkUrlStatus($url) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Mozilla/5.0 (compatible; URLRedirectionBot/1.0)',
            'follow_location' => false, // On gère les redirections manuellement
            'max_redirects' => 0,
            'ignore_errors' => true
        ]
    ]);

    $finalUrl = $url;
    $redirectCount = 0;
    $maxRedirects = 5;

    while ($redirectCount < $maxRedirects) {
        $headers = get_headers($finalUrl, 1, $context);

        if (!$headers) {
            return ['status' => 0, 'final_url' => $url];
        }

        // Extraire le code de statut
        $statusLine = $headers[0];
        preg_match('/HTTP\/\d\.\d\s+(\d+)/', $statusLine, $matches);
        $statusCode = isset($matches[1]) ? intval($matches[1]) : 0;

        // Si c'est un succès, on s'arrête
        if ($statusCode >= 200 && $statusCode < 300) {
            return ['status' => $statusCode, 'final_url' => $finalUrl];
        }

        // Si c'est une redirection, on suit
        if ($statusCode >= 300 && $statusCode < 400) {
            $location = isset($headers['Location']) ? $headers['Location'] : null;

            if (is_array($location)) {
                $location = end($location); // Prendre la dernière si plusieurs
            }

            if ($location) {
                $finalUrl = resolveUrl($location, $finalUrl);
                $redirectCount++;
                continue;
            }
        }

        // Erreur ou pas de redirection
        return ['status' => $statusCode, 'final_url' => $finalUrl];
    }

    return ['status' => 0, 'final_url' => $url];
}

function crawlSiteFromSitemap($siteUrl, $sitemapUrls) {
    $pages = [];
    $maxPages = 200; // Augmenté car on a déjà la liste des URLs

    foreach ($sitemapUrls as $url) {
        if (count($pages) >= $maxPages) {
            break;
        }

        try {
            $content = fetchPageContent($url);
            if ($content) {
                $pages[$url] = [
                    'content' => $content,
                    'title' => extractTitle($content),
                    'text' => extractText($content),
                    'url' => $url
                ];
            }
        } catch (Exception $e) {
            continue;
        }

        // Pause pour éviter de surcharger le serveur
        usleep(50000); // 0.05 seconde (plus rapide car on a déjà les URLs)
    }

    return $pages;
}

function findBestMatchForUrl($searchUrl, $pages) {
    $bestScore = 0;
    $bestUrl = null;

    // Extraire des mots-clés de l'URL de recherche
    $urlKeywords = extractKeywordsFromUrl($searchUrl);

    foreach ($pages as $url => $pageData) {
        $score = calculateUrlSimilarity($searchUrl, $urlKeywords, $pageData);

        if ($score > $bestScore && $score > 0.4) { // Seuil un peu plus élevé
            $bestScore = $score;
            $bestUrl = $url;
        }
    }

    return $bestUrl;
}

function extractKeywordsFromUrl($url) {
    // Extraire le chemin de l'URL
    $path = parse_url($url, PHP_URL_PATH);
    if (!$path) {
        $path = $url;
    }

    // Nettoyer et extraire les mots-clés
    $path = strtolower($path);
    $path = preg_replace('/[^a-z0-9\s]/', ' ', $path);
    $keywords = array_filter(explode(' ', $path), function($word) {
        return strlen($word) > 2; // Ignorer les mots trop courts
    });

    return $keywords;
}

function calculateUrlSimilarity($searchUrl, $urlKeywords, $pageData) {
    $title = strtolower($pageData['title']);
    $content = strtolower($pageData['text']);
    $pageUrl = strtolower($pageData['url']);

    $score = 0;

    // Score basé sur la similarité d'URL (structure, mots-clés)
    $urlScore = 0;
    foreach ($urlKeywords as $keyword) {
        if (strpos($pageUrl, $keyword) !== false) {
            $urlScore += 0.3;
        }
        if (strpos($title, $keyword) !== false) {
            $urlScore += 0.2;
        }
        if (strpos($content, $keyword) !== false) {
            $urlScore += 0.1;
        }
    }
    $score += min($urlScore, 0.6); // Maximum 60% pour l'URL

    // Score basé sur la similarité du titre
    if (!empty($title)) {
        similar_text($searchUrl, $title, $titlePercent);
        $score += ($titlePercent / 100) * 0.3;
    }

    // Score basé sur le contenu
    if (!empty($content)) {
        foreach ($urlKeywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                $score += 0.1;
            }
        }
    }

    return min($score, 1.0); // Maximum 100%
}

function fetchPageContent($url) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Mozilla/5.0 (compatible; URLRedirectionBot/1.0)',
            'follow_location' => true,
            'max_redirects' => 3
        ]
    ]);

    $content = @file_get_contents($url, false, $context);
    return $content;
}

function extractTitle($html) {
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
        return html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8');
    }
    return '';
}

function extractText($html) {
    // Supprimer les scripts et styles
    $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
    $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);

    // Extraire le texte
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\s+/', ' ', $text);

    return trim($text);
}

function resolveUrl($href, $baseUrl) {
    if (filter_var($href, FILTER_VALIDATE_URL)) {
        return $href;
    }

    $baseParts = parse_url($baseUrl);

    if (substr($href, 0, 1) === '/') {
        return $baseParts['scheme'] . '://' . $baseParts['host'] . $href;
    }

    $path = isset($baseParts['path']) ? dirname($baseParts['path']) : '';
    if ($path === '.') $path = '';

    return $baseParts['scheme'] . '://' . $baseParts['host'] . $path . '/' . $href;
}
?>
