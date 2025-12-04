<?php

if ($argc < 2) die("💥 Пример использование: php {$argv[0]} gen.txt\n");

$structureFile = $argv[1];

// Проверяем, существует ли файл с заданием
if (!file_exists($structureFile)) {
    die("💥 Ошибка: Файл структуры '$structureFile' не найден.\n");
}

// Читаем структуру
$lines = file($structureFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$models = parseStructure($lines);
//print_r($models);

// Обрабатываем каждую модель
//foreach ($models as $model => $fields) {
foreach ($models as $title => $model) {
        echo "\n🚀  {$title} -> ".$model['table']."  🚀\n";
    echo "================$title====================\n";

    // generateModel($model);
    // generateMigration($model);    
    // generateService($model);   
    // generateResource($model);    
    // generateController($model);    
    // generateRequests($model);
    // generateApiRoute($model);
    generateAttitude($model);
    continue;

}

// 🔥 Разбираем структуру
function parseStructure($lines)
{
    $models = [];
    $currentModel = null;

    foreach ($lines as $line) {
        // Убираем пробелы, пропускаем пустые строки и комменты 
        if ((trim($line) === '')||strpos(trim($line), '#')) continue;

        // Если строка начинается без пробелов - это модель
        if (strpos($line, ' ') === false && strpos($line, "\t") === false) {
            $model = ucfirst(trim($line));

            // Определяем, является ли таблица пивотной (содержит `_`)
            $isPivot = strpos($model, '_') !== false;
            $lowtable = trim(shell_exec("php artisan tinker --execute=\"echo Illuminate\Support\Str::snake(Illuminate\Support\Str::plural('$model'))\""));
            $table = ucfirst($lowtable); // PascalCase для удобства


            // Создаём структуру модели
            $models[$model] = [
                'model'    => $model,
                'lowmodel' => strtolower($model),
                'table'    => $table,
                'lowtable' => $lowtable,
                'pivot'    => $isPivot ? 1 : 0, // Отметка для пивотных таблиц
                'fields'   => [],
            ];

            $currentModel = $model;
        } 
        // Если строка содержит `=`, значит это параметр
        elseif ($currentModel && strpos($line, '=') !== false) {
            list($field, $definition) = array_map('trim', explode('=', $line, 2));
            $parts = array_map('trim', explode('|', $definition));
            $modifiers = [];

            foreach ($parts as $mod) {
                $mod = trim($mod);
                if (strpos($mod, '(') === false) {
                    $mod .= '()';
                }
                $modifiers[] = $mod;
            }

            // Добавляем в `fields`
            $models[$currentModel]['fields'][$field] = $modifiers;
        }
    }

    return $models;
}





// Функция генерации строк с типами полей
function generateSchemaFields($modelData)
{
    $isPivot = $modelData['pivot'] == 1;
    $fields = $modelData['fields'];
    
    $result = "\$table->id();\n";

    foreach ($fields as $name => $definitions) {
        // print($name);
        // print_r($definitions);
        $result .= parseFieldDefinition($name, $definitions) . ";\n";
    }
    // Если пивотная таблица - timestamps() не нужен
    if (!$isPivot) {
        $result .= "\$table->timestamps();\n";
    } else {
        echo "⚠️  Для пивотных таблиц timestamps не требуется!\n";
    }
    return $result;
}

// Функция обработки типа данных и модификаторов (default, unique, nullable и т. д.)
// Разбираем тип данных и модификаторы
function parseFieldDefinition($name, $definitions)
{
    //$field = "\$table"; // Начинаем с базовой структуры таблицы
    $field = "";
    $isFirst = true; // Флаг для первого типа данных

    foreach ($definitions as $definition) {
        $pos = strpos($definition, '(');
        $mod = $pos !== false ? substr($definition, 0, $pos) : $definition;
        $params = $pos !== false ? substr($definition, $pos + 1, -1) : ''; // Извлекаем параметры
        $params = !empty($params) ? array_map('trim', explode(',', $params)) : []; // Разбиваем в массив

        if ($mod == 'FK') {
            if (count($params) == 2) {
                $field .= "\$table->unsignedBigInteger('$name');\n";
                $field .= "\$table->foreign('$name')->references('{$params[1]}')->on('{$params[0]}')";
            } else {
                $field .= "\$table->foreignId('$name')->constrained()";
            }
            $isFirst = false;
        } elseif ($isFirst) {
            // Обрабатываем основной тип данных
            $paramString = !empty($params) ? implode(', ', $params) : '';
            $field .= "\$table->$mod('$name'" . ($paramString ? ", $paramString" : "") . ")";
            $isFirst = false;
        } else {
            // Добавляем модификаторы (unique, index, nullable)
            $paramString = !empty($params) ? implode(', ', $params) : ''; // Гарантируем, что параметр есть
            $field .= "->$mod" . ($paramString ? "($paramString)" : "()");
        }
    }

    return $field; // Возвращаем корректный Laravel-код
}

function parseFieldDefinition1($name, $definitions)
{
    $field = "\$table"; // Начинаем с базовой структуры таблицы
    $isFirst = true; // Флаг для первого типа данных

    foreach ($definitions as $definition) {
        $pos = strpos($definition, '(');
        $mod = $pos !== false ? substr($definition, 0, $pos) : $definition;
        $params = $pos !== false ? substr($definition, $pos + 1, -1) : ''; // Извлекаем параметры
        $params = !empty($params) ? array_map('trim', explode(',', $params)) : []; // Разбиваем в массив

        if ($mod == 'FK') {
            if (count($params) == 2) {
                // $field .= "->foreign('$name')->references('{$params[1]}')->on('{$params[0]}')";
                $field .= "->unsignedBigInteger('$name');\n";
                $field .= "->foreign('$name')->references('{$params[1]}')->on('{$params[0]}')";
            } else {
                $field .= "->foreignId('$name')->constrained()";
            }
            $isFirst = false;
        } elseif ($isFirst) {
            // Обрабатываем основной тип данных
            $paramString = !empty($params) ? implode(', ', $params) : '';
            $field .= "->$mod('$name'" . ($paramString ? ", $paramString" : "") . ")";
            $isFirst = false;
        } else {
            // Добавляем модификаторы (unique, index, nullable)
            $paramString = !empty($params) ? implode(', ', $params) : ''; // Гарантируем, что параметр есть
            $field .= "->$mod" . ($paramString ? "($paramString)" : "()"); // Безопасное использование
        }
    }

    return $field; // Возвращаем корректный Laravel-код
}


function genValidationStoreRequest($model, $fields, $table)
{
    //$table = trim(shell_exec("php artisan tinker --execute=\"echo(Illuminate\Support\Str::plural('$model'))\""));
    $lowtable=strtolower($table);
    $rules = "return [\n";

    foreach ($fields as $field => $definition) {
        $rules .= "    '{$field}' => '" . parseStoreValidationDefinition($field, $definition, $lowtable) . "',\n";
    }

    $rules .= "];\n";
    return $rules;
}

function parseStoreValidationDefinition($field, $definitions, $table)
{
    $rules = [];

    // Приводим `$definitions` к строке, если это массив
    if (is_array($definitions)) {
        $definitions = implode('|', $definitions);
    }

    // Если это Foreign Key
    if (strpos($definitions, 'FK()') !== false) {
        $rules[] = "exists:" . str_replace('_id', 's,id', $field);
    } else {
        // Определение типа данных
        if (preg_match('/string\((\d+)\)/', $definitions, $match)) {
            $rules[] = 'string';
            $rules[] = "max:{$match[1]}";
        } elseif (strpos($definitions, 'text') !== false) {
            $rules[] = 'string';
        } elseif (strpos($definitions, 'integer') !== false || strpos($definitions, 'tinyInteger') !== false) {
            $rules[] = 'integer';
        } elseif (strpos($definitions, 'boolean') !== false) {
            $rules[] = 'boolean';
        } elseif (strpos($definitions, 'dateTime') !== false) {
            $rules[] = 'date';
        }

        // Проверяем `unique()`
        if (strpos($definitions, 'unique') !== false) {
            $rules[] = "unique:{$table},{$field}";
        }

        // Проверяем `min()`
        if (preg_match('/min\((\d+)\)/', $definitions, $minMatch)) {
            $rules[] = "min:{$minMatch[1]}";
        }

        // Проверяем `max()`
        if (preg_match('/max\((\d+)\)/', $definitions, $maxMatch)) {
            $rules[] = "max:{$maxMatch[1]}";
        }

        // Добавляем `required`, если нет `nullable` или `default`
        if (strpos($definitions, 'default') === false && strpos($definitions, 'nullable') === false) {
            array_unshift($rules, 'required');
        } else {
            array_unshift($rules, 'nullable');
        }
    }

    return implode('|', $rules);
}

function parseStoreValidationDefinition1($field, $definition, $table)
{
    $rules = [];

    // Если это Foreign Key
    if (strpos($definition, 'FK()') !== false) {
        $rules[] = "exists:" . str_replace('_id', 's,id', $field);
    } else {
        // Определение типа данных
        if (preg_match('/string\((\d+)\)/', $definition, $match)) {
            $rules[] = 'string';
            $rules[] = "max:{$match[1]}";
        } elseif (strpos($definition, 'text') !== false) {
            $rules[] = 'string';
        } elseif (strpos($definition, 'integer') !== false || strpos($definition, 'tinyInteger') !== false) {
            $rules[] = 'integer';
        } elseif (strpos($definition, 'boolean') !== false) {
            $rules[] = 'boolean';
        } elseif (strpos($definition, 'dateTime') !== false) {
            $rules[] = 'date';
        }

        // Проверяем `unique()`
        if (strpos($definition, 'unique') !== false) {
            $rules[] = "unique:{$table},{$field}";
        }

        // Проверяем `min()`
        if (preg_match('/min\((\d+)\)/', $definition, $minMatch)) {
            $rules[] = "min:{$minMatch[1]}";
        }

        // Проверяем `max()`
        if (preg_match('/max\((\d+)\)/', $definition, $maxMatch)) {
            $rules[] = "max:{$maxMatch[1]}";
        }

        // Добавляем `required`, если нет `nullable` или `default`
        if (strpos($definition, 'default') === false && strpos($definition, 'nullable') === false) {
            array_unshift($rules, 'required');
        } else {
            array_unshift($rules, 'nullable');
        }
    }

    return implode('|', $rules);
}

         
function genValidationUpdateRequest($model, $fields, $table)
{
    //$table = trim(shell_exec("php artisan tinker --execute=\"echo(Illuminate\Support\Str::plural('$model'))\""));
    $lowtable=strtolower($table);
    $rules = "return [\n";

    foreach ($fields as $field => $definition) {
        $rules .= "    '{$field}' => '" . parseUpdateValidationDefinition($field, $definition, $lowtable) . "',\n";
    }

    $rules .= "];\n";
    return $rules;
}

// 🔹 Функция разбора атрибутов и формирования правил валидации для UpdateRequest

function parseUpdateValidationDefinition($field, $definitions, $table)
{
    $rules = [];

    // Приводим `$definitions` к строке, если это массив
    if (is_array($definitions)) {
        $definitions = implode('|', $definitions);
    }

    // Если это Foreign Key
    if (strpos($definitions, 'FK()') !== false) {
        $rules[] = "exists:" . str_replace('_id', 's,id', $field);
    } else {
        // Определение типа данных
        if (preg_match('/string\((\d+)\)/', $definitions, $match)) {
            $rules[] = 'string';
            $rules[] = "max:{$match[1]}";
        } elseif (strpos($definitions, 'text') !== false) {
            $rules[] = 'string';
        } elseif (strpos($definitions, 'integer') !== false || strpos($definitions, 'tinyInteger') !== false) {
            $rules[] = 'integer';
        } elseif (strpos($definitions, 'boolean') !== false) {
            $rules[] = 'boolean';
        } elseif (strpos($definitions, 'dateTime') !== false) {
            $rules[] = 'date';
        }

        // Проверяем `unique()`
        if (strpos($definitions, 'unique') !== false) {
            $rules[] = "unique:{$table},{$field},{\$this->id}";
        }

        // Проверяем `min()`
        if (preg_match('/min\((\d+)\)/', $definitions, $minMatch)) {
            $rules[] = "min:{$minMatch[1]}";
        }

        // Проверяем `max()`
        if (preg_match('/max\((\d+)\)/', $definitions, $maxMatch)) {
            $rules[] = "max:{$maxMatch[1]}";
        }

        // Добавляем `sometimes`, чтобы поле не было обязательным при обновлении
        array_unshift($rules, 'sometimes');

        // Добавляем `nullable`, если указано
        if (strpos($definitions, 'default') !== false || strpos($definitions, 'nullable') !== false) {
            $rules[] = 'nullable';
        }
    }

    return implode('|', $rules);
}



// CLI Check Create Control
function CCCC($path, $cmd, $refresh = true)
{
    // Используем glob() для поиска файлов (даже если в имени есть дата)
    $existingFiles = glob($path);

    // Если файлы уже существуют
    if (!empty($existingFiles)) {
        if ($refresh) {
            foreach ($existingFiles as $file) {
                echo "🗑  Удаляем файл: $file\n";
                unlink($file);
            }
        } else {
            echo "✅  Файл уже существует: {$existingFiles[0]}\n";
            return true; 
        }
    }

    // Запускаем команду CLI
    echo "🚀  Выполняем команду: $cmd\n";
    shell_exec($cmd);

    // Повторно проверяем, создан ли файл
    $newFiles = glob($path);

    if (!empty($newFiles)) {
        echo "✅  Файл успешно создан: {$newFiles[0]}\n";
        return true;
    } else {
        echo "❌  Файл не создан: {$path}\n";
        return false;
    }
}

function putCodeInFile($filePath, $search, $putCode)
{
    $code = file_get_contents($filePath);

    $startPos = strpos($code, $search);
    if ($startPos === false) {
        echo "❌  Ошибка: строка '{$search}' не найдена в файле.\n";
        return false;
    }

    // Найти первую { после искомой строки
    $braceOpenPos = strpos($code, '{', $startPos);
    if ($braceOpenPos === false) {
        echo "❌  Ошибка: не найдена { после '{$search}'.\n";
        return false;
    }

    // Найти соответствующую }
    $braceCount = 1;
    $pos = $braceOpenPos + 1;

    while ($braceCount > 0 && $pos < strlen($code)) {
        if ($code[$pos] === '{') {
            $braceCount++;
        } elseif ($code[$pos] === '}') {
            $braceCount--;
        }
        $pos++;
    }

    if ($braceCount > 0) {
        echo "❌  Ошибка: не найдена закрывающая } для '{$search}'.\n";
        return false;
    }

    // Добавляем `fillable` сразу после открывающей `{`, без лишних пробелов
    $newCode = substr($code, 0, $braceOpenPos + 1) . "\n    " . $putCode . "\n" . substr($code, $braceOpenPos + 1);

    // Записываем обновлённый код обратно в файл
    file_put_contents($filePath, $newCode);

    return true;
}

function replaceCodeInFile($filePath, $search, $putCode)
{
    // Проверяем доступность файла
    if (!file_exists($filePath) || !is_readable($filePath) || !is_writable($filePath)) {
        echo "❌ Ошибка: файл '{$filePath}' недоступен для чтения или записи.\n";
        return false;
    }

    $code = file_get_contents($filePath);

    // Найти позицию начала искомой строки
    $startPos = strpos($code, $search);
    if ($startPos === false) {
        echo "❌ Ошибка: строка '{$search}' не найдена в файле.\n";
        return false;
    }

    // Найти первую { после искомой строки
    $braceOpenPos = strpos($code, '{', $startPos);
    if ($braceOpenPos === false) {
        echo "❌ Ошибка: не найдена { после '{$search}'.\n";
        return false;
    }

    // Определяем начало строки с `{`
    $lineStart = strrpos(substr($code, 0, $braceOpenPos), "\n") + 1;
    $lineWithBrace = substr($code, $lineStart, $braceOpenPos - $lineStart);

    // Определяем отступ перед `{`
    preg_match('/^\s*/', $lineWithBrace, $matches);
    $indentation = $matches[0] ?? '';

    // Добавляем 4 пробела к каждой строке вставляемого кода
    $shiftedCode = implode("\n", array_map(fn($line) => $indentation . "    " . $line, explode("\n", $putCode)));

    // Найти соответствующую закрывающую }
    $braceCount = 1;
    $pos = $braceOpenPos + 1;
    $codeLength = strlen($code);

    while ($braceCount > 0 && $pos < $codeLength) {
        if ($code[$pos] === '{') {
            $braceCount++;
        } elseif ($code[$pos] === '}') {
            $braceCount--;
        }
        $pos++;
    }

    if ($braceCount > 0) {
        echo "❌ Ошибка: не найдена закрывающая } для '{$search}'.\n";
        return false;
    }

    // Формируем новое содержимое с заменой кода внутри {}
    $formattedCode = "\n" . $shiftedCode . "\n" . $indentation;
    //$formattedCode = "\n" . $shiftedCode;
    $newCode = substr($code, 0, $braceOpenPos + 1) . $formattedCode . substr($code, $pos - 1);

    // Записываем обновлённый код обратно в файл
    file_put_contents($filePath, $newCode);

    return true;
}

function generateModel($model)
{
    if (str_contains($model['model'], '_')) {
        echo "⚠️  Для пивотных таблиц моедь не требуется!\n";
        return 0;
    }
   
    $filePath = "app/Models/{$model['model']}.php";
    $artisanCmd = "php artisan make:model {$model['model']}";

    if (CCCC($filePath, $artisanCmd, true)) {
        $fileContent = file_get_contents($filePath);
        $search = "class {$model['model']} extends Model";
        //$fillable = "'" . implode("', '", array_keys($model)) . "'";
        $fillable = "'" . implode("', '", array_keys($model['fields'])) . "'";
        $putCode = "protected \$fillable = [$fillable];";
        if (replaceCodeInFile($filePath, $search, $putCode)) echo "✅  Класс $model переписаны.\n";
    };
}

function generateMigration($modelData)
{
    $model = $modelData['model'];
    $fields = $modelData['fields'];
    $table = $modelData['table'];
    $isPivot = $modelData['pivot'] == 1;
    $lowtable = $modelData['lowmodel'];

    $filePathPattern = "database/migrations/*_create_".$lowtable."_table.php";
    $artisanCmd = "php artisan make:migration create_".$lowtable."_table --create=$lowtable";

    if (CCCC($filePathPattern, $artisanCmd, true)) {
        $migrationFiles = glob($filePathPattern);
        if (!empty($migrationFiles)) {
            $migrationFile = $migrationFiles[0]; // Берём первый найденный файл
            $search = "Schema::create('$lowtable', function (Blueprint \$table)";
            $putCode = generateSchemaFields($modelData);


            echo "✅  Обновляем миграцию: $migrationFile\n";

            if (replaceCodeInFile($migrationFile, $search, $putCode)) {
                echo "✅  Миграция $model переписана.\n";
            }
        } else {
            echo "❌  Миграция не найдена\n";
        }
    }
}

function generateService($modelData)
{
    $model = $modelData['model'];
    $lowModel = strtolower($model);
    
    if (str_contains($model, '_')) {
        echo "⚠️  Для пивотных таблиц Service не требуется!\n";
        return false;
    }
    
    $filePath = "app/Services/{$model}Service.php";
    $artisanCmd = "php artisan make:class Services/{$model}Service";
    
    if (CCCC($filePath, $artisanCmd, true)) {
        $fileContent = file_get_contents($filePath);

        // if (!preg_match("/use\s+{$model};/", $fileContent)) {
        //     $fileContent = preg_replace('/(namespace\s+[\w\\\\]+;)/', "$1\n\nuse App\Models\\$model;", $fileContent, 1);
        // }
        
        // Добавляем методы store() и update(), если их нет
        if (!preg_match('/public\s+static\s+function\s+store/', $fileContent)) {
            $methods = "\n";
            
            // store
            $methods .= "    public static function store(array \$data): {$model}\n";
            $methods .= "    {\n";
            $methods .= "        return {$model}::create(\$data);\n";
            $methods .= "    }\n\n";

            // update
            $methods .= "    public static function update({$model} \$entity, array \$data): {$model}\n";
            $methods .= "    {\n";
            $methods .= "        \$entity->update(\$data);\n";
            $methods .= "        return \$entity;\n";
            $methods .= "    }\n\n";

            $fileContent = preg_replace('/}\s*$/', $methods . "}\n", $fileContent);
        }
        
        
        // Записываем обновлённое содержимое обратно в файл
        file_put_contents($filePath, $fileContent);

        echo "✅  Сервис {$model}Service обновлён!\n";
    } else {
        echo "❌  Ошибка: файл не создан - $filePath\n";
    }
}

function generateService1($model){
    if (str_contains($model, '_')) {
        echo "⚠️  Для пивотных таблиц Service не требуется!\n";
        return 0;
    }
    $filelPath = "app/Services/{$model}Service.php";
    $artisanCmd = "php artisan make:class Services/{$model}Service";
    if (CCCC($filelPath, $artisanCmd, true)) {
        $fileContent = file_get_contents($filelPath);

        if (!preg_match("/use\s+{$model};/", $fileContent)) {
            $fileContent = preg_replace('/(namespace\s+[\w\\\\]+;)/', "$1\n\nuse App\Models\\$model;", $fileContent, 1);
        }

        // Добавляем методы `store()` и `update()`, если их нет
        if (!preg_match('/public\s+static\s+function\s+store/', $fileContent)) {
            $methods = ""; // Инициализация пустой строки
            // store
            $methods .= "    public static function store(array \$data): {$model}\n";
            $methods .= "    {\n";
            $methods .= "        return {$model}::create(\$data);\n";
            $methods .= "    }\n\n";
            // update
            $methods .= "    public static function update({$model} \$entity, array \$data): {$model}\n";
            $methods .= "    {\n";
            $methods .= "        \$entity->update(\$data);\n";
            $methods .= "        return \$entity;\n";
            $methods .= "    }\n\n";
            
            $fileContent = preg_replace('/}\s*$/', $methods . "}\n", $fileContent);
        }

        // Записываем обновлённое содержимое обратно в файл
        file_put_contents($filelPath, $fileContent);
    } else {
        echo "❌  Ошибка: файл не создан - $filelPath\n"; 
    }
}

// Функция обновления ресурсного файла
function generateResource($modelData)
{
    $model = $modelData['model'];
    
    if (str_contains($model, '_')) {
        echo "⚠️  Для пивотных таблиц Resource не требуется!\n";
        return false;
    }
    
    $filePath = "app/Http/Resources/{$model}/{$model}Resource.php";
    $artisanCmd = "php artisan make:resource {$model}/{$model}Resource";
    
    if (CCCC($filePath, $artisanCmd, true)) {
        $fileContent = file_get_contents($filePath);
        
        // Формируем массив свойств
        $properties = [];
        foreach ($modelData['fields'] as $name => $definition) {
            $properties[] = "            '{$name}' => \$this->{$name}";
        }
        $properties[] = "            'updated_at' => \$this->updated_at->format('Y-m-d H:i:s')";
        
        $propertiesString = implode(",\n", $properties);
        
        // Генерируем метод toArray()
        $toArrayMethod = "public function toArray(Request \$request): array\n";
        $toArrayMethod .= "    {\n";
        $toArrayMethod .= "        return [\n";
        $toArrayMethod .= "$propertiesString\n";
        $toArrayMethod .= "        ];\n";
        $toArrayMethod .= "    }\n";
        
        // Удаляем `return parent::toArray($request);`, если он есть
        $fileContent = preg_replace(
            '/public\s+function\s+toArray\(Request\s+\$request\):\s*array\s*{.*?return\s*parent::toArray\(\$request\);.*?}/s',
            $toArrayMethod,
            $fileContent
        );
        
        // Если `toArray()` не найден, добавляем его перед `}`
        if (!preg_match('/public\s+function\s+toArray\(/', $fileContent)) {
            echo "🆕  Добавляем `toArray()` в {$model}Resource.php\n";
            $fileContent = preg_replace('/}\s*$/', "\n$toArrayMethod}\n", $fileContent);
        }
        
        // Записываем обновлённое содержимое обратно в файл
        file_put_contents($filePath, $fileContent);
        echo "✅  Файл {$model}Resource.php обновлён!\n";
    } else {
        echo "❌  Ошибка: файл не создан - $filePath\n";
    }
}


function generateController($modelData)
{
    $model = $modelData['model'];
    
    if (str_contains($model, '_')) {
        echo "⚠️  Для пивотных таблиц Controller не требуется!\n";
        return false;
    }
    
    $filePath = "app/Http/Controllers/Api/{$model}Controller.php";
    $artisanCmd = "php artisan make:controller Api/{$model}Controller --api -m {$model}";
    
    if (CCCC($filePath, $artisanCmd, true)) {
        $fileContent = file_get_contents($filePath);
        
        // Добавляем use для моделей и сервисов, если они отсутствуют
        // $imports = [
        //     "use App\\Models\\{$model};",
        //     "use App\\Http\\Resources\\{$model}\\{$model}Resource;",
        //     "use App\\Http\\Requests\\Api\\{$model}\\StoreRequest;",
        //     "use App\\Http\\Requests\\Api\\{$model}\\UpdateRequest;",
        //     "use App\\Services\\{$model}Service;",
        // ];
        
        // foreach ($imports as $import) {
        //     if (!preg_match("/" . preg_quote($import, '/') . "/", $fileContent)) {
        //         $fileContent = preg_replace('/(namespace\s+[\w\\\\]+;)/', "$1\n" . implode("\n", $imports), $fileContent, 1);

        //        // $fileContent = preg_replace('/(namespace\s+[\w\\]+;)/', "$1\n" . implode("\n", $imports), $fileContent, 1);
        //     }
        // }

        $imports = [
            "use App\Http\Requests\Api\\$model\StoreRequest;",
            "use App\Http\Requests\Api\\$model\UpdateRequest;",
            "use App\Http\Resources\\{$model}\\{$model}Resource;",
            "use App\Services\\{$model}Service;",
        ];

        foreach ($imports as $import) {
            if (!preg_match("/" . preg_quote($import, '/') . "/", $fileContent)) {
                $fileContent = preg_replace('/(namespace\s+[\w\\\\]+;)/', "$1\n" . implode("\n", $imports), $fileContent, 1);
            }
        }
        file_put_contents($filePath, $fileContent);
        // $search = "public function index()";
        // $putCode = "return {$model}Resource::collection({$model}::all());\n";
        // if (replaceCodeInFile($filePath, $search, $putCode)) {
        //    // echo "✅  Миграция $model переписана.\n";
        // }

        // $search = "public function store(StoreRequest \$request)";
        // $putCode = "\$data = \$request->validated();\n";
        // $putCode .= "\$entity = {$model}Service::store(\$data);\n";
        // $putCode .= "return new {$model}Resource(\$entity);\n";
        
        // if (replaceCodeInFile($filePath, $search, $putCode)) {
        //    // echo "✅  Миграция $model переписана.\n";
        // }


        // Генерируем методы контроллера
        $methods = "";
        
        //index()
        $methods .= "public function index()\n";
        $methods .= "{\n";
        $methods .= "    return {$model}Resource::collection({$model}::all());\n";
        $methods .= "}\n\n";
        
        // store()
        $methods .= "public function store(StoreRequest \$request)\n";
        $methods .= "{\n";
        $methods .= "    \$data = \$request->validated();\n";
        $methods .= "    \$entity = {$model}Service::store(\$data);\n";
        $methods .= "    return new {$model}Resource(\$entity);\n";
        $methods .= "}\n\n";
        
        // show()
        $methods .= "public function show({$model} \$entity)\n";
        $methods .= "{\n";
        $methods .= "    return new {$model}Resource(\$entity);\n";
        $methods .= "}\n\n";
        
        // update()
        $methods .= "public function update(UpdateRequest \$request, {$model} \$entity)\n";
        $methods .= "{\n";
        $methods .= "    \$data = \$request->validated();\n";
        $methods .= "    \$entity = {$model}Service::update(\$entity, \$data);\n";
        $methods .= "    return new {$model}Resource(\$entity);\n";
        $methods .= "}\n\n";
        
        // destroy()
        $methods .= "public function destroy({$model} \$entity)\n";
        $methods .= "{\n";
        $methods .= "    \$id = \$entity->id;\n";
        $methods .= "    \$title = \$entity->title ?? '';\n";
        $methods .= "    \$entity->delete();\n\n";
        $methods .= "    return response([\n";
        $methods .= "        'message' => \"{$model}: \$id (\$title) успешно удалён\",\n";
        $methods .= "    ], 200);\n";
        $methods .= "}\n";
        
        

        $search = "class {$model}Controller extends Controller";
        $putCode = "return {$model}Resource::collection({$model}::all());\n";
        if (replaceCodeInFile($filePath, $search, $methods)) {
            echo "✅  Контроллер {$model}Controller создан и обновлён!\n";
        }        
    } else {
        echo "❌  Ошибка: контроллер не создан - $filePath\n";
    }
}

function generateRequests($modelData)
{
    $model = $modelData['model'];
    $fields = $modelData['fields'];
    $table = $modelData['table'];
    
    if (str_contains($model, '_')) {
        echo "⚠️  Для пивотных таблиц Requests не требуется!\n";
        return false;
    }
    
    // StoreRequest
    $storeRequestPath = "app/Http/Requests/Api/{$model}/StoreRequest.php";
    $storeCmd = "php artisan make:request Api/{$model}/StoreRequest";
    
    if (CCCC($storeRequestPath, $storeCmd, true)) {
        $search = "public function authorize(): bool";
        $putCode = "return true;";
        replaceCodeInFile($storeRequestPath, $search, $putCode);
        
        $search = "public function rules(): array";

        $putCode = genValidationStoreRequest($model, $fields, $table);
        print_r($model);
        print_r($fields);
        print_r($table);
        print_r($putCode);
        

        replaceCodeInFile($storeRequestPath, $search, $putCode);
       // exit;
        echo "✅ StoreRequest для {$model} обновлён.\n";
    } else {
        echo "❌ Ошибка: файл не создан - $storeRequestPath\n";
    }
    
    // UpdateRequest
    $updateRequestPath = "app/Http/Requests/Api/{$model}/UpdateRequest.php";
    $updateCmd = "php artisan make:request Api/{$model}/UpdateRequest";
    
    if (CCCC($updateRequestPath, $updateCmd, true)) {
        $search = "public function authorize(): bool";
        $putCode = "return true;";
        replaceCodeInFile($updateRequestPath, $search, $putCode);
        
        $search = "public function rules(): array";
        $putCode = genValidationUpdateRequest($model, $fields, $table);
        replaceCodeInFile($updateRequestPath, $search, $putCode);
        
        echo "✅ UpdateRequest для {$model} обновлён.\n";
    } else {
        echo "❌ Ошибка: файл не создан - $updateRequestPath\n";
    }
}

function generateRequests1($model, $fields)
{
    if (str_contains($model, '_')) {
        echo "⚠️  Для пивотных таблиц Requests не требуется!\n";
        return 0;
    }
    $filelPath = "app/Http/Requests/Api/{$model}/StoreRequest.php";
    $artisanCmd = "php artisan make:request Api/{$model}/StoreRequest";
    if (CCCC($filelPath, $artisanCmd, true)) {
        $fileContent = file_get_contents($filelPath);
        
        $search = "public function authorize(): bool";
        $putCode = "        return true;";
        if (replaceCodeInFile($filelPath, $search, $putCode)) echo "✅  Отключил авторизацию.\n";
        $search = "public function rules(): array";
        $putCode = genValidationStoreRequest($model, $fields, $table);
        if (replaceCodeInFile($filelPath, $search, $putCode)) echo "✅  StoreRequest для  {$model} переписаны.\n";
    } else {
        echo "❌  Ошибка: файл не создан - $filelPath\n"; 
    }

    $filelPath = "app/Http/Requests/Api/{$model}/UpdateRequest.php";
    $artisanCmd = "php artisan make:request Api/{$model}/UpdateRequest";
    if (CCCC($filelPath, $artisanCmd, true)) {
        $fileContent = file_get_contents($filelPath);
        $search = "public function authorize(): bool";
        $putCode = "        return true;";
        if (replaceCodeInFile($filelPath, $search, $putCode)) echo "✅  Отключил авторизацию.\n";
        $search = "public function rules(): array";
        $putCode = genValidationUpdateRequest($model, $fields, $table);
        
        if (replaceCodeInFile($filelPath, $search, $putCode)) echo "✅  UpdateRequest для  {$model} переписаны.\n";
    } else {
        echo "❌  Ошибка: файл не создан - $filelPath\n"; 
    }
}

function generateApiRoute($modelData)
{
    $model = $modelData['model'];
    if (str_contains($model, '_')) {
        echo "⚠️  Для пивотных таблиц ApiRoute не требуется!\n";
        return 0;
    }
    $filelPath = "routes/api.php";
    $controller = "App\\Http\\Controllers\\Api\\{$model}Controller";
    $routeDefinition = "Route::apiResource('" . strtolower($model) . "s', {$model}Controller::class);";

    // Проверяем, существует ли файл маршрутов
    if (!file_exists($filelPath)) {
        echo "❌  Ошибка: файл маршрутов не найден - $filelPath\n";
        return false;
    }

    // Читаем содержимое файла
    $fileContent = file_get_contents($filelPath);

    // Добавляем `use` для контроллера, если его нет
    $useStatement = "use {$controller};";
    if (!preg_match("/" . preg_quote($useStatement, '/') . "/", $fileContent)) {
        $fileContent = preg_replace('/<\?php\s*/', "<?php\n\n{$useStatement}\n", $fileContent, 1);
    }

    // Добавляем маршрут, если его ещё нет
    if (!preg_match("/" . preg_quote($routeDefinition, '/') . "/", $fileContent)) {
        $fileContent .= "{$routeDefinition}\n";
        echo "✅  Добавлен маршрут API для {$model}\n";
    } else {
        echo "⚠️  Маршрут API для {$model} уже существует\n";
    }

    // Записываем обновлённый файл
    file_put_contents($filelPath, $fileContent);
    return true;
}

function studly(string $value): string
{
	$value = str_replace(['-', '_'], ' ', $value);
	$value = ucwords($value);
	return str_replace(' ', '', $value);
}

function camel(string $value): string
{
	$studly = studly($value);
	return lcfirst($studly);
}

function generateAttitude($modelData)
{
	$model = $modelData['model'];

	echo "==============================\n";
	echo "🔗 Генерация связей для модели {$model}\n";

	// Пивотам обычно модель не нужна → связи не трогаем
	if (str_contains($model, '_')) {
		echo "⚠️  {$model} выглядит как пивотная таблица (имя с '_') — связи не генерируем.\n";
		return false;
	}

	$filePath = "app/Models/{$model}.php";

	if (!file_exists($filePath)) {
		echo "❌  Файл модели не найден: {$filePath}. Пропускаем.\n";
		return false;
	}

	$code = file_get_contents($filePath);
	$relationsCode = [];
	$added = 0;

	foreach ($modelData['fields'] as $field => $mods) {
		$firstMod = $mods[0] ?? null;

		if (!$firstMod || strpos($firstMod, 'FK') !== 0) {
			continue; // поле не FK
		}

		$relationName = null;
		$targetClass  = null;
		$foreignKey   = null;

		// Вариант 1: FK() — выводим из имени поля, например category_id → Category
		if ($firstMod === 'FK()') {
			if (!str_ends_with($field, '_id')) {
				echo "⚠️  Поле {$field} помечено как FK(), но не оканчивается на '_id' — пропускаем.\n";
				continue;
			}

			$base = substr($field, 0, -3); // обрезаем _id
			$relationName = camel($base);  // category_id → category
			$targetClass  = studly($base); // category_id → Category
			$foreignKey   = $field;        // явный fk
		}
		// Вариант 2: FK(Model, field) — явное указание
		else {
			$inner = substr($firstMod, 3, -1); // вырезаем FK(....)
			$values = array_map('trim', explode(',', $inner));
			$target = $values[0] ?? null;
			$fk     = $values[1] ?? $field;

			if (!$target) {
				echo "⚠️  Некорректный FK модификатор у поля {$field}: {$firstMod}\n";
				continue;
			}

			$targetClass  = studly($target);
			$relationName = camel($target);
			$foreignKey   = $fk;
		}

		// Защита от дублей: если метод уже есть в модели — пропускаем
		if (strpos($code, "function {$relationName}(") !== false) {
			echo "⚠️  Связь {$relationName}() уже существует в {$model}, пропускаем.\n";
			continue;
		}

		// Собираем текст метода (без ведущих отступов — их добавит appendCodeBefore)
		$method  = "public function {$relationName}(): BelongsTo\n";
		$method .= "{\n";
		if ($firstMod === 'FK()') {
			$method .= "    return \$this->belongsTo({$targetClass}::class);\n";
		} else {
			$method .= "    return \$this->belongsTo({$targetClass}::class, '{$foreignKey}');\n";
		}
		$method .= "}\n";

		$relationsCode[] = $method;
		$added++;
	}

	if ($added === 0) {
		echo "ℹ️  Для модели {$model} FK-полей не найдено, или все связи уже существуют.\n";
		return false;
	}

	// Добавляем use BelongsTo, если его ещё нет
	if (strpos($code, 'BelongsTo') === false) {
		if (preg_match('/namespace\s+[^;]+;/', $code, $matches, PREG_OFFSET_CAPTURE)) {
			$nsEndPos = $matches[0][1] + strlen($matches[0][0]);
			$before   = substr($code, 0, $nsEndPos);
			$after    = substr($code, $nsEndPos);
			$code     = $before . "\n\nuse Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;\n" . $after;
			file_put_contents($filePath, $code);
			echo "✅  Добавлен use BelongsTo в {$filePath}\n";
		} else {
			echo "⚠️  Не удалось найти namespace в {$filePath}, use BelongsTo не добавлен.\n";
		}
	}

	// Вставляем все методы перед закрывающей фигурной скобкой класса
	$block = implode("\n", $relationsCode) . "\n";

	if (appendCodeBefore($filePath, "class {$model} extends Model", $block)) {
		echo "✅  В модель {$model} добавлено {$added} связ(и).\n";
		return true;
	}

	echo "❌  Не удалось вставить методы связей в модель {$model}.\n";
	return false;
}


function appendCodeBefore($filePath, $search, $codeToAdd)
{
    // Проверяем доступность файла
    if (!file_exists($filePath) || !is_readable($filePath) || !is_writable($filePath)) {
        echo "❌ Ошибка: файл '{$filePath}' недоступен для чтения или записи.\n";
        return false;
    }

    $code = file_get_contents($filePath);

    // Найти позицию начала искомой строки
    $startPos = strpos($code, $search);
    if ($startPos === false) {
        echo "❌ Ошибка: строка '{$search}' не найдена в файле.\n";
        return false;
    }

    // Найти первую { после искомой строки
    $braceOpenPos = strpos($code, '{', $startPos);
    if ($braceOpenPos === false) {
        echo "❌ Ошибка: не найдена { после '{$search}'.\n";
        return false;
    }

    // Найти соответствующую закрывающую }
    $braceCount = 1;
    $pos = $braceOpenPos + 1;
    $codeLength = strlen($code);

    while ($braceCount > 0 && $pos < $codeLength) {
        if ($code[$pos] === '{') {
            $braceCount++;
        } elseif ($code[$pos] === '}') {
            $braceCount--;
        }
        $pos++;
    }

    if ($braceCount > 0) {
        echo "❌ Ошибка: не найдена закрывающая } для '{$search}'.\n";
        return false;
    }

    // Определяем отступ перед закрывающей }
    $lineStart = strrpos(substr($code, 0, $pos - 1), "\n") + 1;
    $lineWithBrace = substr($code, $lineStart, $pos - 1 - $lineStart);

    // Определяем отступ перед `}`
    preg_match('/^\s*/', $lineWithBrace, $matches);
    $indentation = $matches[0] ?? '';

    // Добавляем отступ к каждой строке добавляемого кода
    $shiftedCode = implode("\n", array_map(fn($line) => $indentation . "    " . $line, explode("\n", $codeToAdd)));

    // Формируем новое содержимое с добавлением кода перед }
    $formattedCode = "\n" . $shiftedCode . "\n" . $indentation;
    $newCode = substr($code, 0, $pos - 1) . $formattedCode . substr($code, $pos - 1);

    // Записываем обновлённый код обратно в файл
    file_put_contents($filePath, $newCode);

    return true;
}