<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Compatibility;

use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\Utils\Str;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

class HttpRequestFpmWrapper extends HttpRequestCompatibilityBase
{
    private $headers_lowercase;

    private $server_params_lowercase;

    private $parsed_body;

    /**
     * Retrieves all message header values.
     *
     * The keys represent the header name as it will be sent over the wire, and
     * each value is an array of strings associated with the header.
     *
     *     // Represent the headers as a string
     *     foreach ($message->getHeaders() as $name => $values) {
     *         echo $name . ": " . implode(", ", $values);
     *     }
     *
     *     // Emit headers iteratively:
     *     foreach ($message->getHeaders() as $name => $values) {
     *         foreach ($values as $value) {
     *             header(sprintf('%s: %s', $name, $value), false);
     *         }
     *     }
     *
     * While header names are not case-sensitive, getHeaders() will preserve the
     * exact case in which headers were originally specified.
     *
     * @return string[][] Returns an associative array of the message's headers. Each
     *                    key MUST be a header name, and each value MUST be an array of strings
     *                    for that header.
     */
    public function getHeaders()
    {
        if ($this->headers_lowercase === null) {
            $this->headers_lowercase = [];
            foreach ($_SERVER as $key => $value) {
                if (!Str::startsWith($key, 'HTTP_')) {
                    continue;
                }
                $key = substr(strtolower(str_replace('_', '-', $key)), 5);
                $this->headers_lowercase[$key] = $value;
            }
        }
        return $this->headers_lowercase;
    }

    /**
     * Gets the body of the message.
     *
     * @return StreamInterface returns the body as a stream
     */
    public function getBody()
    {
        return new SwooleStream(file_get_contents('php://input'));
    }

    /**
     * Retrieve server parameters.
     *
     * Retrieves data related to the incoming request environment,
     * typically derived from PHP's $_SERVER superglobal. The data IS NOT
     * REQUIRED to originate from $_SERVER.
     *
     * @return array
     */
    public function getServerParams()
    {
        if ($this->server_params_lowercase === null) {
            $this->server_params_lowercase = [];
            foreach ($_SERVER as $key => $value) {
                if (Str::startsWith($key, 'HTTP_')) {
                    continue;
                }
                $this->server_params_lowercase[strtolower($key)] = $value;
            }
        }
        return $this->server_params_lowercase;
    }

    /**
     * Retrieve cookies.
     *
     * Retrieves cookies sent by the client to the server.
     *
     * The data MUST be compatible with the structure of the $_COOKIE
     * superglobal.
     *
     * @return array
     */
    public function getCookieParams()
    {
        return $_COOKIE;
    }

    /**
     * Retrieve query string arguments.
     *
     * Retrieves the deserialized query string arguments, if any.
     *
     * Note: the query params might not be in sync with the URI or server
     * params. If you need to ensure you are only getting the original
     * values, you may need to parse the query string from `getUri()->getQuery()`
     * or from the `QUERY_STRING` server param.
     *
     * @return array
     */
    public function getQueryParams()
    {
        return $_GET;
    }

    /**
     * Retrieve normalized file upload data.
     *
     * This method returns upload metadata in a normalized tree, with each leaf
     * an instance of Psr\Http\Message\UploadedFileInterface.
     *
     * These values MAY be prepared from $_FILES or the message body during
     * instantiation, or MAY be injected via withUploadedFiles().
     *
     * @return array an array tree of UploadedFileInterface instances; an empty
     *               array MUST be returned if no data is present
     */
    public function getUploadedFiles()
    {
        return $_FILES;
    }

    /**
     * Retrieve any parameters provided in the request body.
     *
     * If the request Content-Type is either application/x-www-form-urlencoded
     * or multipart/form-data, and the request method is POST, this method MUST
     * return the contents of $_POST.
     *
     * Otherwise, this method may return any results of deserializing
     * the request body content; as parsing returns structured content, the
     * potential types MUST be arrays or objects only. A null value indicates
     * the absence of body content.
     *
     * @return null|array|object The deserialized body parameters, if any.
     *                           These will typically be an array or object.
     */
    public function getParsedBody()
    {
        if ($this->parsed_body === null) {
            $content_type = $this->header('content-type', null);
            if (!empty($content_type) && Str::startsWith($content_type, 'application/json')) {
                $this->parsed_body = json_decode(file_get_contents('php://input'));
            } else {
                $this->parsed_body = $_POST;
            }
        }
        return $this->parsed_body;
    }

    /**
     * Retrieve attributes derived from the request.
     *
     * The request "attributes" may be used to allow injection of any
     * parameters derived from the request: e.g., the results of path
     * match operations; the results of decrypting cookies; the results of
     * deserializing non-form-encoded message bodies; etc. Attributes
     * will be application and request specific, and CAN be mutable.
     *
     * @return array attributes derived from the request
     */
    public function getAttributes()
    {
        throw new RuntimeException('not implemented');
    }

    /**
     * Retrieve the data from route parameters.
     * @param mixed $default
     */
    public function route(string $key, $default = null)
    {
        throw new RuntimeException('not implemented');
    }
}
