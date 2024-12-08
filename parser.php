<?php
namespace App;

use Exception;

class Product
{
    public string $brand_name;
    public string $model_name;
    public ?string $colour_name;
    public ?string $gb_spec_name;
    public ?string $network_name;
    public ?string $grade_name;
    public ?string $condition_name;

    public function __construct(
        string $brand_name,
        string $model_name,
        ?string $colour_name,
        ?string $gb_spec_name,
        ?string $network_name,
        ?string $grade_name,
        ?string $condition_name
    ) {
        $this->brand_name = $brand_name;
        $this->model_name = $model_name;
        $this->colour_name = $colour_name;
        $this->gb_spec_name = $gb_spec_name;
        $this->network_name = $network_name;
        $this->grade_name = $grade_name;
        $this->condition_name = $condition_name;
    }

    public static function fromArray(array $data): self
    {
        $requiredFields = ['brand_name', 'model_name'];

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        return new self(
            $data['brand_name'],
            $data['model_name'],
            $data['colour_name'] ?? null,
            $data['gb_spec_name'] ?? null,
            $data['network_name'] ?? null,
            $data['grade_name'] ?? null,
            $data['condition_name'] ?? null
        );
    }

    public function __toString(): string
    {
        return json_encode(get_object_vars($this), JSON_PRETTY_PRINT);
    }
}

class ProductParser
{
    public function parseFile(string $filePath): iterable
    {
        $fileHandle = fopen($filePath, 'r');

        if (!$fileHandle) {
            throw new Exception("Unable to open file: $filePath");
        }

        $headers = fgetcsv($fileHandle);
        if (!$headers) {
            throw new Exception("Failed to read headers from file: $filePath");
        }

        while (($row = fgetcsv($fileHandle)) !== false) {
            yield array_combine($headers, $row);
        }

        fclose($fileHandle);
    }
}

class CombinationCounter
{
    public function generateCounts(string $filePath, string $outputFile): void
    {
        $parser = new ProductParser();
        $combinations = [];

        foreach ($parser->parseFile($filePath) as $row) {
            $product = Product::fromArray($row);
            $key = json_encode([
                $product->brand_name,
                $product->model_name,
                $product->colour_name,
                $product->gb_spec_name,
                $product->network_name,
                $product->grade_name,
                $product->condition_name
            ]);

            if (!isset($combinations[$key])) {
                $combinations[$key] = 0;
            }

            $combinations[$key]++;
        }

        $outputHandle = fopen($outputFile, 'w');
        if (!$outputHandle) {
            throw new Exception("Unable to open file for writing: $outputFile");
        }

        // Write the headings row
        fputcsv($outputHandle, [
            'Make', 'Model', 'Colour', 'Capacity', 'Network', 'Grade', 'Condition', 'Count'
        ]);

        foreach ($combinations as $key => $count) {
            $data = json_decode($key, true);
            $data['count'] = $count;
            fputcsv($outputHandle, $data);
        }

        fclose($outputHandle);
    }
}

//Checking if code is run by cli command
if (php_sapi_name() === 'cli') {
    $options = getopt('', ['file:', 'unique-combinations:']);

    //Throw an error if any of these two paramerts aren't present
    if (!isset($options['file'], $options['unique-combinations'])) {
        echo "Usage: php parser.php --file=products_comma_separated.csv --unique-combinations=combination_count.csv\n";
        exit(1);
    }

    $file = $options['file'];
    $output = $options['unique-combinations'];

    try {
        $counter = new CombinationCounter();
        $counter->generateCounts($file, $output);
        echo "File processed successfully , please check the output file $output\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
