<?php

namespace App;

final class Parser
{
    public function parse(string $inputPath, string $outputPath): void
    {
        $handle = fopen($inputPath, 'r');
        $flat = [];
        $leftover = '';
        $chunkSize = 16 * 1024 * 1024;

        while (!feof($handle)) {
            $raw = fread($handle, $chunkSize);
            if ($raw === false || $raw === '') {
                break;
            }

            $chunk = $leftover . $raw;

            $lastNl = strrpos($chunk, "\n");
            if ($lastNl === false) {
                $leftover = $chunk;
                continue;
            }

            $chunkLen = strlen($chunk);
            $leftover = ($lastNl < $chunkLen - 1) ? substr($chunk, $lastNl + 1) : '';
            $endPos = $lastNl + 1;

            $pos = 0;
            while ($pos < $endPos) {
                $pathStart = $pos + 19;
                $commaPos = strpos($chunk, ',', $pathStart);

                $key = substr($chunk, $pathStart, $commaPos + 11 - $pathStart);

                if (isset($flat[$key])) {
                    $flat[$key]++;
                } else {
                    $flat[$key] = 1;
                }

                $pos = $commaPos + 27;
            }
        }

        if ($leftover !== '') {
            $commaPos = strpos($leftover, ',', 19);
            if ($commaPos !== false) {
                $key = substr($leftover, 19, $commaPos + 11 - 19);
                if (isset($flat[$key])) {
                    $flat[$key]++;
                } else {
                    $flat[$key] = 1;
                }
            }
        }

        fclose($handle);

        // Decompose flat keys into nested structure (preserves path insertion order)
        $data = [];
        foreach ($flat as $key => $count) {
            $commaPos = strrpos($key, ',');
            $path = substr($key, 0, $commaPos);
            $date = substr($key, $commaPos + 1);
            $data[$path][$date] = $count;
        }
        unset($flat);

        // Sort dates within each path
        foreach ($data as &$dates) {
            ksort($dates);
        }
        unset($dates);

        file_put_contents($outputPath, json_encode($data, JSON_PRETTY_PRINT));
    }
}
