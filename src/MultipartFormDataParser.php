<?php

namespace RgLaravelLib\Laravel\Request;


use Illuminate\Http\Request;

/**
 * Class MultipartFormDataParser
 *
 * @package RgLaravelLib\Laravel\Request\MultipartFormData
 */

class MultipartFormDataParser
{
    /**
     * The Http Request.
     *
     * @var \Illuminate\Http\Request  $request
     */
    protected $request;


    /**
     * The Http request method.
     *
     * @var string $method
     */
    protected $method;

    /**
     * Create a new MultipartFormDataParser instance.
     *
     * @param  \Illuminate\Http\Request|null  $request
     * @return void
     */
    public function __construct(Request &$request, string $method)
    {
        $this->request = $request;
        $this->method = $method;
        $this->parse();
    }

    /**
     * 
     * 
     * @return void
     */
    private function parse()
    {
        if (!empty($this->request->getContent())) {
            $this->parseContent();
        }
    }

    private function parseContent(): void
    {
        preg_match('/boundary=(.*)$/is', $this->request->headers->get('content-type'), $matches);

        if (count($matches) == 2) {
            $encapsulationBoundary  = $matches[1];
            $boundaryParts = preg_split('/\\R?-+' . preg_quote($encapsulationBoundary, '/') . '/s', $this->request->getContent());

            array_pop($boundaryParts);

            foreach ($boundaryParts as $boundaryPart) {
                if (empty($boundaryPart)) {
                    continue;
                }
                $this->processContent($boundaryPart);
            }
        }
    }

    private function processContent(string $boundaryPart): void
    {
        $boundaryPart = ltrim($boundaryPart, "\r\n");
        [$headers, $content] = explode("\r\n\r\n", $boundaryPart, 2);
        $headers = explode("\r\n", $headers);

        foreach ($headers as $header) {

            preg_match('/name=(.*)$/is', $header, $matches);
            if (!str_contains($header, ':') && count($matches) != 2 && trim($matches[1]) <= 0) {
                continue;
            }

            [$headerName, $value] = explode(':', $header, 2);

            if (strtolower(trim($headerName)) != 'content-disposition') {
                continue;
            }

            $this->parseContentDisposition(ltrim($value, ' '), $content);
        }
    }


    private function parseContentDisposition(string $headerName, string $content): void
    {
        $content = substr($content, 0, strlen($content) - 2);
        preg_match('/^form-data; *name="([^"]+)"(; *filename="([^"]+)")?/', $headerName, $matches);
        $fieldName = $matches[1];
        $fileName = $matches[3] ?? null;

        if (is_null($fileName)) {
            $input = $this->transformContent($fieldName, $content);

            //   $this->inputs = array_merge_recursive($this->inputs, $input);
        } else {
            $file = $this->storeFile($fileName, $headers['content-type'], $content);

            $file = $this->transformContent($fieldName, $file);

            // $this->files = array_merge_recursive($this->files, $file);
        }
    }

    private function transformContent(string $name, mixed $value): array
    {
        parse_str($name, $parsedName);

        $transform = function (array $array, mixed $value) use (&$transform) {
            foreach ($array as &$val) {
                $val = is_array($val) ? $transform($val, $value) : $value;
            }

            return $array;
        };

        return $transform($parsedName, $value);
    }

    private function storeFile(string $name, string $type, string $content): array
    {
        $file = [
            'name' => $name,
            'type' => $type,
            'size' => mb_strlen($value, '8bit'),
            'error' => UPLOAD_ERR_OK,
            'tmp_name' => null,
        ];

        if ($file['size'] > self::toBytes(ini_get('upload_max_filesize'))) {
            $file['error'] = UPLOAD_ERR_INI_SIZE;
        } else {
            $tmpResource = tmpfile();
            if ($tmpResource === false) {
                $file['error'] = UPLOAD_ERR_CANT_WRITE;
            } else {
                $tmpResourceMetaData = stream_get_meta_data($tmpResource);
                $tmpFileName = $tmpResourceMetaData['uri'];
                if (empty($tmpFileName)) {
                    $file['error'] = UPLOAD_ERR_CANT_WRITE;
                    @fclose($tmpResource);
                } else {
                    fwrite($tmpResource, $value);
                    $file['tmp_name'] = $tmpFileName;
                    $file['tmp_resource'] = $tmpResource;
                }
            }
        }
        $file["size"] = self::toFormattedBytes($file["size"]);
        $_FILES[$headers['content-disposition']['name']] = $file;

        $tempName = tempnam(sys_get_temp_dir(), 'MultipartFormData_');

        file_put_contents($tempName, $content);

        register_shutdown_function(function () use ($tempName): void {
            if (file_exists($tempName)) {
                unlink($tempName);
            }
        });

        return [
            'name' => $name,
            'type' => $type,
            'tmp_name' => $tempName,
            'error' => 0,
            'size' => filesize($tempName),
        ];
    }
}
