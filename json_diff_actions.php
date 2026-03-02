<?php
            $leg = json_decode(file_get_contents(__DIR__ . "/legacy_actions_out.json"), true);
            $lar = json_decode(file_get_contents(__DIR__ . "/laravel_actions_out.json"), true);
            
            function compare($path, $v1, $v2) {
                if (is_array($v1) && is_array($v2)) {
                    $keys = array_unique(array_merge(array_keys($v1), array_keys($v2)));
                    foreach ($keys as $k) {
                        if (!array_key_exists($k, $v1)) echo "Missing in legacy: $path.$k\n";
                        elseif (!array_key_exists($k, $v2)) echo "Missing in Laravel: $path.$k\n";
                        else compare("$path.$k", $v1[$k], $v2[$k]);
                    }
                } else {
                    if ($v1 !== $v2) {
                        echo "Mismatch at $path: Legacy=".json_encode($v1).", Laravel=".json_encode($v2)."\n";
                        echo "  Types: Legacy=".gettype($v1).", Laravel=".gettype($v2)."\n\n";
                    }
                }
            }
            compare("root", $leg, $lar);
        