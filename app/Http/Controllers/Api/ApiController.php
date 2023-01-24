<?php

namespace App\Http\Controllers\Api;

use App\Enum\HttpResponseCodes;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ApiController extends Controller
{
    /**
     * Resource name
     * @var string
     */
    protected $resourceName = '';


    /**
     * Return generic json response with the given data.
     *
     * @param array   $data        Response data.
     * @param integer $respondCode Response status code.
     * @param array   $headers     Response headers.
     * @return \Illuminate\Http\JsonResponse
     */
    public function respondJson(array $data, int $respondCode = HttpResponseCodes::HTTP_OK, array $headers = [])
    {
        return response()->json($data, $respondCode, $headers);
    }

    /**
     * Return forbidden message
     *
     * @param string $message Response message.
     * @return \Illuminate\Http\JsonResponse
     */
    public function respondForbidden(string $message = 'You do not have permission to access the resource.')
    {
        return response()->json(['message' => $message], HttpResponseCodes::HTTP_FORBIDDEN);
    }

    /**
     * Return forbidden message
     *
     * @param string $message Response message.
     * @return \Illuminate\Http\JsonResponse
     */
    public function respondNotFound(string $message = 'The resource was not found.')
    {
        return response()->json(['message' => $message], HttpResponseCodes::HTTP_NOT_FOUND);
    }

    /**
     * Return too large message
     *
     * @param string $message Response message.
     * @return \Illuminate\Http\JsonResponse
     */
    public function respondTooLarge(string $message = 'The request entity is too large.')
    {
        return response()->json(['message' => $message], HttpResponseCodes::HTTP_REQUEST_ENTITY_TOO_LARGE);
    }

    /**
     * Return no content
     * @return \Illuminate\Http\JsonResponse
     */
    public function respondNoContent()
    {
        return response()->json([], HttpResponseCodes::HTTP_NO_CONTENT);
    }

    /**
     * Return created
     * @return \Illuminate\Http\JsonResponse
     */
    public function respondCreated()
    {
        return response()->json([], HttpResponseCodes::HTTP_CREATED);
    }

    /**
     * Return single error message
     *
     * @param string  $message      Error message.
     * @param integer $responseCode Resource code.
     * @return \Illuminate\Http\JsonResponse
     */
    public function respondError(string $message, int $responseCode = HttpResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
    {
        return response()->json([
            'message' => $message
        ], $responseCode);
    }

    /**
     * Return formatted errors
     *
     * @param array   $errors       Error messages.
     * @param integer $responseCode Resource code.
     * @return \Illuminate\Http\JsonResponse
     */
    public function respondWithErrors(array $errors, int $responseCode = HttpResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
    {
        $keys = array_keys($errors);
        $error = $errors[$keys[0]];

        if (count($keys) > 1) {
            $additional_errors = (count($keys) - 1);
            $error .= sprintf(' (and %d more %s', $additional_errors, Str::plural('error', $additional_errors));
        }

        return response()->json([
            'message' => $error,
            'errors' => $errors
        ], $responseCode);
    }

    /**
     * Return resource data
     *
     * @param array|Model|Collection $data        Resource data.
     * @param array|null             $appendData  Data to append to response.
     * @param integer                $respondCode Resource code.
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondAsResource(
        mixed $data,
        mixed $appendData = null,
        int $respondCode = HttpResponseCodes::HTTP_OK
    ) {
        if ($data === null || ($data instanceof Collection && $data->count() === 0)) {
            return $this->respondNotFound();
        }

        $resourceName = $this->resourceName;

        if ($this->resourceName === '') {
            $resourceName = get_class($this);
            $resourceName = substr($resourceName, (strrpos($resourceName, '\\') + 1));
            $resourceName = substr($resourceName, 0, strpos($resourceName, 'Controller'));
            $resourceName = strtolower($resourceName);
        }

        $is_multiple = true;

        $dataArray = [];
        if ($data instanceof Collection) {
            $dataArray = $data->toArray();
        } elseif (is_array($data) === true) {
            $dataArray = $data;
        } elseif ($data instanceof Model) {
            $is_multiple = false;
            $dataArray = $data->toArray();
        }

        $resource = [];
        if ($is_multiple === true) {
            $resource = [Str::plural($resourceName) => $dataArray];
        } else {
            $resource = [Str::singular($resourceName) => $dataArray];
        }

        if ($appendData !== null) {
            $resource += $appendData;
        }

        return response()->json($resource, $respondCode);
    }
}
