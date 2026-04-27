<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST allowed']);
    exit;
}

$url = json_decode(file_get_contents('php://input'), true)['url'] ?? '';

if (empty($url)) {
    echo json_encode(['success' => false, 'message' => 'URL required']);
    exit;
}

// Instagram video extractor using third-party API
function extractInstagramVideo($url) {
    // Validate Instagram URL
    if (!preg_match('/instagram\.com\/(p|reel)\/([A-Za-z0-9_-]+)/', $url, $matches)) {
        return false;
    }
    
    $shortcode = $matches[2];
    
    // Use RapidAPI Instagram Downloader
    $api_url = "https://instagram-downloader.p.rapidapi.com/";
    $api_key = "YOUR_RAPIDAPI_KEY"; // Replace with your actual key from https://rapidapi.com/
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['url' => $url]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-RapidAPI-Key: ' . $api_key,
            'X-RapidAPI-Host: instagram-downloader.p.rapidapi.com'
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || empty($response)) {
        return false;
    }
    
    $data = json_decode($response, true);
    if (!$data || !isset($data['data'])) {
        return false;
    }
    
    $videoData = $data['data'];
    
    // Assuming the API returns video URLs in different qualities
    $downloadLinks = [];
    if (isset($videoData['hd'])) {
        $downloadLinks['hd'] = ['mp4' => $videoData['hd']];
    }
    if (isset($videoData['sd'])) {
        $downloadLinks['sd'] = ['mp4' => $videoData['sd']];
    }
    if (isset($videoData['ld'])) {
        $downloadLinks['ld'] = ['mp4' => $videoData['ld']];
    }
    
    // If no specific qualities, use the main video
    if (empty($downloadLinks) && isset($videoData['url'])) {
        $downloadLinks = [
            'hd' => ['mp4' => $videoData['url']],
            'sd' => ['mp4' => $videoData['url']],
            'ld' => ['mp4' => $videoData['url']]
        ];
    }
    
    if (empty($downloadLinks)) {
        return false;
    }
    
    return [
        'title' => $videoData['title'] ?? 'Instagram Video',
        'preview' => $videoData['thumbnail'] ?? $downloadLinks['hd']['mp4'],
        'download_links' => $downloadLinks
    ];
}

// Call the function
$result = extractInstagramVideo($url);
if ($result) {
    echo json_encode(['success' => true, 'data' => $result]);
} else {
    echo json_encode(['success' => false, 'message' => 'Unable to extract video from the provided URL']);
}