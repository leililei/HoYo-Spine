<?php
function parseSpineAtlas($content)
{
    $lines = explode("\n", $content);

    $result = [];
    $currentPngIndex = 0;
    $isRegionStart = false;
    $currentRegion = null;

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
            $isRegionStart = false;
        }
        if (preg_match('/(^.*?)\.png$/', $line, $match)) {
            $currentPng = $match[1];
            $result[$currentPngIndex]['file'] = $currentPng;
            continue;
        }

        if (preg_match('/(^.*?):(.*?$)/', $line, $match)) {
            if (!$isRegionStart) {
                $result[$currentPngIndex][$match[1]] = explode(',', $match[2]);
            } else {
                $result[$currentPngIndex]['regions'][$currentRegion][$match[1]] = explode(',', $match[2]);
            }
            continue;
        } else {
            $isRegionStart = true;
            $currentRegion = $line;
        }
    }

    return $result;
}
// 获取当前目录下所有.atlas文件
$atlasFiles = glob('*.atlas');
$jsonFiles = glob('*.json');
$pngFiles = glob('*.png');
$fileMap = [];
foreach ($atlasFiles as $atlasFile) {
    // 读取文件第一行
    $atlas = parseSpineAtlas(file_get_contents($atlasFile));
    print_r($atlas);
    $baseName = $atlas[0]['file'] ?? 0;
    if (!$baseName) {
        continue;
    }
    $atlasBaseName = pathinfo($atlasFile, PATHINFO_FILENAME); // 获取无扩展名的基础名
    echo "即将处理文件: $baseName\n";
    $fileMap[$baseName]['ori_atlas'] = $atlasFile;
    $fileMap[$baseName]['atlas'] = $baseName . '/' . $baseName . '.atlas';
    $fileMap[$baseName]['png'] =  $baseName . '/' . $baseName . '.png';
    $fileMap[$baseName]['json'] = $baseName . '/' . $baseName . '.json';
    // 检查每个json文件
    $associatedJsonFiles = [];

    foreach ($jsonFiles as $jsonFile) {
        $jsonContent = file_get_contents($jsonFile);
        $containsAll = true;

        // 检查是否包含所有区域
        foreach (array_keys($atlas[0]['regions']) as $region) {
            if (strpos($jsonContent, '"name":"' . $region . '"') === false and strpos($jsonContent, '"attachment":"' . $region . '"') === false) {
                $containsAll = false;
                break;
            }
        }

        if ($containsAll) {
            $associatedJsonFiles[] = basename($jsonFile);
        }
    }
    $fileMap[$baseName]['possible_json'] = $associatedJsonFiles;
    //检查图片尺寸
    $associatedPngFiles = [];
    foreach ($pngFiles as $pngFile) {
        $pngFile = realpath($pngFile);
        $pngInfo = getimagesize($pngFile);
        if ($pngInfo[0] == $atlas[0]['size'][0] && $pngInfo[1] == $atlas[0]['size'][1]) {
            $associatedPngFiles[] = basename($pngFile);
        }
    }
    $fileMap[$baseName]['possible_png'] = $associatedPngFiles;
}
foreach ($fileMap as $key => $value) {
    if (count($value['possible_json']) === 1 and count($value['possible_png']) === 1) {
        @mkdir($key);
        rename($value['ori_atlas'],  $value['atlas']);
        rename($value['possible_png'][0], $value['png']);
        rename($value['possible_json'][0], $value['json']);
    }
}
echo "脚本执行完成\n";
