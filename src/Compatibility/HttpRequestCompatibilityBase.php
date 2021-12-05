<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Compatibility;

use Hyperf\HttpMessage\Upload\UploadedFile;
use Hyperf\HttpMessage\Uri\Uri;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Utils\Arr;
use Hyperf\Utils\Str;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use SplFileInfo;

/**
 * 环境兼容的 request 基类
 * 1. 不实现所有 with 开头的方法
 * 2. 部分公用方法实现.
 */
abstract class HttpRequestCompatibilityBase implements RequestInterface
{
    private $input_data;

    /**
     * Retrieves the HTTP protocol version as a string.
     *
     * The string MUST contain only the HTTP version number (e.g., "1.1", "1.0").
     *
     * @return string HTTP protocol version
     */
    public function getProtocolVersion()
    {
        $server_protocol = $this->getServerParams()['server_protocol'];
        return explode('/', $server_protocol, 2)[1];
    }

    /**
     * Checks if a header exists by the given case-insensitive name.
     *
     * @param string $name case-insensitive header field name
     * @return bool Returns true if any header names match the given header
     *              name using a case-insensitive string comparison. Returns false if
     *              no matching header name is found in the message.
     */
    public function hasHeader($name)
    {
        return Arr::has($this->getHeaders(), strtolower($name));
    }

    /**
     * Retrieves a message header value by the given case-insensitive name.
     *
     * This method returns an array of all the header values of the given
     * case-insensitive header name.
     *
     * If the header does not appear in the message, this method MUST return an
     * empty array.
     *
     * @param string $name case-insensitive header field name
     * @return string[] An array of string values as provided for the given
     *                  header. If the header does not appear in the message, this method MUST
     *                  return an empty array.
     */
    public function getHeader($name)
    {
        $name = strtolower($name);
        if ($this->hasHeader($name)) {
            $headers = $this->getHeaders();
            return is_array($headers[$name]) ? $headers[$name] : [$headers[$name]];
        }
        return [];
    }

    /**
     * Retrieves a comma-separated string of the values for a single header.
     *
     * This method returns all of the header values of the given
     * case-insensitive header name as a string concatenated together using
     * a comma.
     *
     * NOTE: Not all header values may be appropriately represented using
     * comma concatenation. For such headers, use getHeader() instead
     * and supply your own delimiter when concatenating.
     *
     * If the header does not appear in the message, this method MUST return
     * an empty string.
     *
     * @param string $name case-insensitive header field name
     * @return string A string of values as provided for the given header
     *                concatenated together using a comma. If the header does not appear in
     *                the message, this method MUST return an empty string.
     */
    public function getHeaderLine($name)
    {
        return join(',', $this->getHeader($name));
    }

    /**
     * Retrieve the input data from request, include query parameters, parsed body and json body.
     * @param mixed $default
     */
    public function input(string $key, $default = null)
    {
        $data = $this->getInputData();
        return data_get($data, $key, $default);
    }

    /**
     * Retrieve the input data from request via multi keys, include query parameters, parsed body and json body.
     * @param mixed $default
     */
    public function inputs(array $keys, $default = null): array
    {
        $data = $this->getInputData();
        $result = $default ?? [];
        foreach ($keys as $key) {
            $result[$key] = data_get($data, $key);
        }
        return $result;
    }

    /**
     * Retrieves the HTTP method of the request.
     *
     * @return string returns the request method
     */
    public function getMethod()
    {
        return $this->getServerParams()['request_method'];
    }

    /**
     * Retrieve all input data from request, include query parameters, parsed body and json body.
     */
    public function all(): array
    {
        return $this->getInputData();
    }

    /**
     * Retrieve the data from query parameters, if $key is null, will return all query parameters.
     * @param mixed $default
     */
    public function query(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->getQueryParams();
        }
        return data_get($this->getQueryParams(), $key, $default);
    }

    /**
     * Retrieve the data from parsed body, if $key is null, will return all parsed body.
     * @param mixed $default
     */
    public function post(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->getParsedBody();
        }
        return data_get($this->getParsedBody(), $key, $default);
    }

    /**
     * Determine if the $keys is exist in parameters.
     *
     * @param array|string $keys
     */
    public function has($keys): bool
    {
        return Arr::has($this->getInputData(), $keys);
    }

    /**
     * Retrieve the data from request headers.
     * @param mixed $default
     */
    public function header(string $key, $default = null)
    {
        if (!$this->hasHeader($key)) {
            return $default;
        }
        return $this->getHeaderLine($key);
    }

    /**
     * Determine if the current request URI matches a pattern.
     *
     * @param mixed ...$patterns
     */
    public function is(...$patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $this->decodedPath())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the current decoded path info for the request.
     */
    public function decodedPath(): string
    {
        return rawurldecode($this->path());
    }

    /**
     * Returns the requested URI (path and query string).
     *
     * @return string The raw URI (i.e. not URI decoded)
     */
    public function getRequestUri()
    {
        return $this->getServerParams()['request_uri'] ?? null;
    }

    /**
     * Retrieve a cookie from the request.
     * @param null|mixed $default
     */
    public function cookie(string $key, $default = null)
    {
        return data_get($this->getCookieParams(), $key, $default);
    }

    /**
     * Determine if a cookie is set on the request.
     */
    public function hasCookie(string $key): bool
    {
        return !is_null($this->cookie($key));
    }

    /**
     * Determine if the $keys is exist in parameters.
     * @return []array [found, not-found]
     */
    public function hasInput(array $keys): array
    {
        $data = $this->getInputData();
        $found = [];
        foreach ($keys as $key) {
            if (Arr::has($data, $key)) {
                $found[] = $key;
            }
        }

        return [$found, array_diff($keys, $found)];
    }

    /**
     * Get the URL (no query string) for the request.
     */
    public function url(): string
    {
        return rtrim(preg_replace('/\?.*/', '', $this->getUri()), '/');
    }

    /**
     * Get the full URL for the request.
     */
    public function fullUrl(): string
    {
        $query = $this->getQueryString();
        return $this->url() . '?' . $query;
    }

    /**
     * Generates the normalized query string for the Request.
     *
     * It builds a normalized query string, where keys/value pairs are alphabetized
     * and have consistent escaping.
     *
     * @return null|string A normalized query string for the Request
     */
    public function getQueryString(): ?string
    {
        $qs = static::normalizeQueryString($this->getServerParams()['query_string'] ?? '');
        return $qs === '' ? null : $qs;
    }

    /**
     * Normalizes a query string.
     *
     * It builds a normalized query string, where keys/value pairs are alphabetized,
     * have consistent escaping and unneeded delimiters are removed.
     *
     * @param string $qs Query string
     * @return string A normalized query string for the Request
     */
    public function normalizeQueryString(string $qs): string
    {
        if ($qs === '') {
            return '';
        }
        parse_str($qs, $query_string_array);
        ksort($query_string_array);
        return http_build_query($query_string_array, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Retrieve a server variable from the request.
     *
     * @param null|mixed $default
     * @return null|array|string
     */
    public function server(string $key, $default = null)
    {
        return data_get($this->getServerParams(), $key, $default);
    }

    /**
     * Checks if the request method is of specified type.
     *
     * @param string $method Uppercase request method (GET, POST etc)
     */
    public function isMethod(string $method): bool
    {
        return $this->getMethod() === strtoupper($method);
    }

    /**
     * Retrieve a file from the request.
     *
     * @param null|mixed $default
     * @return null|UploadedFile|UploadedFile[]
     */
    public function file(string $key, $default = null)
    {
        return Arr::get($this->getUploadedFiles(), $key, $default);
    }

    /**
     * Determine if the uploaded data contains a file.
     */
    public function hasFile(string $key): bool
    {
        if ($file = $this->file($key)) {
            return $this->isValidFile($file);
        }
        return false;
    }

    /**
     * Retrieves the URI instance.
     *
     * This method MUST return a UriInterface instance.
     *
     * @see http://tools.ietf.org/html/rfc3986#section-4.3
     * @return UriInterface returns a UriInterface instance
     *                      representing the URI of the request
     */
    public function getUri()
    {
        return new Uri($this->getRequestUri());
    }

    /**
     * Retrieves the message's request target.
     * Retrieves the message's request-target either as it will appear (for
     * clients), as it appeared at request (for servers), or as it was
     * specified for the instance (see withRequestTarget()).
     * In most cases, this will be the origin-form of the composed URI,
     * unless a value was provided to the concrete implementation (see
     * withRequestTarget() below).
     * If no URI is available, and no request-target has been specifically
     * provided, this method MUST return the string "/".
     *
     * @return string
     */
    public function getRequestTarget()
    {
        $target = $this->getUri()->getPath();
        if ($target == '') {
            $target = '/';
        }
        if ($this->getUri()->getQuery() != '') {
            $target .= '?' . $this->getUri()->getQuery();
        }

        return $target;
    }

    /**
     * Returns the path being requested relative to the executed script.
     * The path info always starts with a /.
     * Suppose this request is instantiated from /mysite on localhost:
     *  * http://localhost/mysite              returns an empty string
     *  * http://localhost/mysite/about        returns '/about'
     *  * http://localhost/mysite/enco%20ded   returns '/enco%20ded'
     *  * http://localhost/mysite/about?var=1  returns '/about'.
     *
     * @return string The raw path (i.e. not urldecoded)
     */
    public function getPathInfo(): string
    {
        return $this->getServerParams()['path_info'];
    }

    /**
     * Retrieve a single derived request attribute.
     *
     * Retrieves a single derived request attribute as described in
     * getAttributes(). If the attribute has not been previously set, returns
     * the default value as provided.
     *
     * This method obviates the need for a hasAttribute() method, as it allows
     * specifying a default value to return if the attribute is not found.
     *
     * @param string $name the attribute name
     * @param mixed $default default value to return if the attribute does not exist
     * @return mixed
     * @see getAttributes()
     */
    public function getAttribute($name, $default = null)
    {
        return data_get($this->getAttributes(), $name, $default);
    }

    //--------- with 开头的方法不做实现 ---------//

    public function withProtocolVersion($version)
    {
        throw new RuntimeException('not implemented');
    }

    public function withHeader($name, $value)
    {
        throw new RuntimeException('not implemented');
    }

    public function withAddedHeader($name, $value)
    {
        throw new RuntimeException('not implemented');
    }

    public function withoutHeader($name)
    {
        throw new RuntimeException('not implemented');
    }

    public function withBody(StreamInterface $body)
    {
        throw new RuntimeException('not implemented');
    }

    public function withMethod($method)
    {
        throw new RuntimeException('not implemented');
    }

    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        throw new RuntimeException('not implemented');
    }

    public function withRequestTarget($requestTarget)
    {
        throw new RuntimeException('not implemented');
    }

    public function withCookieParams(array $cookies)
    {
        throw new RuntimeException('not implemented');
    }

    public function withQueryParams(array $query)
    {
        throw new RuntimeException('not implemented');
    }

    public function withUploadedFiles(array $uploadedFiles)
    {
        throw new RuntimeException('not implemented');
    }

    public function withParsedBody($data)
    {
        throw new RuntimeException('not implemented');
    }

    public function withAttribute($name, $value)
    {
        throw new RuntimeException('not implemented');
    }

    public function withoutAttribute($name)
    {
        throw new RuntimeException('not implemented');
    }

    public function route(string $key, $default = null)
    {
        throw new RuntimeException('not implemented');
    }

    protected function getInputData(): array
    {
        if ($this->input_data === null) {
            $this->input_data = array_merge($this->getParsedBody(), $this->getQueryParams());
        }
        return $this->input_data;
    }

    /**
     * Get the current path info for the request.
     *
     * @return string
     */
    protected function path()
    {
        $pattern = trim($this->getPathInfo(), '/');
        return $pattern == '' ? '/' : $pattern;
    }

    /**
     * Check that the given file is a valid SplFileInfo instance.
     * @param mixed $file
     */
    protected function isValidFile($file): bool
    {
        return $file instanceof SplFileInfo && $file->getPath() !== '';
    }

    /**
     * Return an UploadedFile instance array.
     *
     * @param array $files A array which respect $_FILES structure
     * @throws \InvalidArgumentException for unrecognized values
     * @return array
     */
    protected function normalizeFiles(array $files)
    {
        $normalized = [];

        foreach ($files as $key => $value) {
            if ($value instanceof UploadedFileInterface) {
                $normalized[$key] = $value;
            } elseif (is_array($value) && isset($value['tmp_name'])) {
                $normalized[$key] = $this->createUploadedFileFromSpec($value);
            } elseif (is_array($value)) {
                $normalized[$key] = $this->normalizeFiles($value);
                continue;
            } else {
                throw new InvalidArgumentException('Invalid value in files specification');
            }
        }

        return $normalized;
    }

    /**
     * Create and return an UploadedFile instance from a $_FILES specification.
     * If the specification represents an array of values, this method will
     * delegate to normalizeNestedFileSpec() and return that return value.
     *
     * @param array $value $_FILES struct
     * @return array|UploadedFileInterface
     */
    private function createUploadedFileFromSpec(array $value)
    {
        if (is_array($value['tmp_name'])) {
            return self::normalizeNestedFileSpec($value);
        }

        return new UploadedFile($value['tmp_name'], (int) $value['size'], (int) $value['error'], $value['name'], $value['type']);
    }

    /**
     * Normalize an array of file specifications.
     * Loops through all nested files and returns a normalized array of
     * UploadedFileInterface instances.
     *
     * @return UploadedFileInterface[]
     */
    private function normalizeNestedFileSpec(array $files = [])
    {
        $normalizedFiles = [];

        foreach (array_keys($files['tmp_name']) as $key) {
            $spec = [
                'tmp_name' => $files['tmp_name'][$key],
                'size' => $files['size'][$key],
                'error' => $files['error'][$key],
                'name' => $files['name'][$key],
                'type' => $files['type'][$key],
            ];
            $normalizedFiles[$key] = $this->createUploadedFileFromSpec($spec);
        }

        return $normalizedFiles;
    }
}
