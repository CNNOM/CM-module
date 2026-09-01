<?php
declare(strict_types=1);

namespace CloudMill\App\Infrastructure\Bitrix\Presentation;

use CBitrixComponentTemplate;

final class ComponentAssets
{
    public static function includeJS(CBitrixComponentTemplate $template): ?string
    {

        $scriptPath = $template->__folder . '/script.js';
        if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $scriptPath)) {
            return null;
        }

        return <<<JS
            document.addEventListener('DOMContentLoaded', function() {
                const scriptPath = '$scriptPath';
                
                const scripts = document.getElementsByTagName('script');
                let scriptExists = false;
                
                for (let i = 0; i < scripts.length; i++) {
                    const src = scripts[i].getAttribute('src');
                    if (src && src.includes(scriptPath)) {
                        scriptExists = true;
                        break;
                    }
                }
                
                if (!scriptExists) {
                    const scriptElement = document.createElement('script');
                    scriptElement.src = scriptPath;
                    scriptElement.type = 'text/javascript';
                    document.head.appendChild(scriptElement);
                    console.log('Script added:', scriptPath);
                } else {
                    console.log('Script already exists:', scriptPath);
                }
            });
JS;

    }
}
