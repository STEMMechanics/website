<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
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
     * @return JsonResponse
     */
    public function respondJson(
        array $data,
        int $respondCode = HttpResponseCodes::HTTP_OK,
        array $headers = []
    ): JsonResponse {
        return response()->json($data, $respondCode, $headers);
    }

    /**
     * Return forbidden message
     *
     * @param string $message Response message.
     * @return JsonResponse
     */
    public function respondForbidden(
        string $message = 'You do not have permission to access the resource.'
    ): JsonResponse {
        return response()->json(['message' => $message], HttpResponseCodes::HTTP_FORBIDDEN);
    }

    /**
     * Return forbidden message
     *
     * @param string $message Response message.
     * @return JsonResponse
     */
    public function respondNotFound(string $message = 'The resource was not found.'): JsonResponse
    {
        return response()->json(['message' => $message], HttpResponseCodes::HTTP_NOT_FOUND);
    }

    /**
     * Return too large message
     *
     * @param string $message Response message.
     * @return JsonResponse
     */
    public function respondTooLarge(string $message = 'The request entity is too large.'): JsonResponse
    {
        return response()->json(['message' => $message], HttpResponseCodes::HTTP_REQUEST_ENTITY_TOO_LARGE);
    }

    /**
     * Return no content.
     *
     * @return JsonResponse
     */
    public function respondNoContent(): JsonResponse
    {
        return response()->json([], HttpResponseCodes::HTTP_NO_CONTENT);
    }

    /**
     * Return no content
     *
     * @return JsonResponse
     */
    public function respondNotImplemented(): JsonResponse
    {
        return response()->json([], HttpResponseCodes::HTTP_NOT_IMPLEMENTED);
    }

    /**
     * Return created.
     *
     * @return JsonResponse
     */
    public function respondCreated(): JsonResponse
    {
        return response()->json([], HttpResponseCodes::HTTP_CREATED);
    }

    /**
     * Return accepted.
     *
     * @return JsonResponse
     */
    public function respondAccepted(): JsonResponse
    {
        return response()->json([], HttpResponseCodes::HTTP_ACCEPTED);
    }

    /**
     * Return server error.
     *
     * @return JsonResponse
     */
    public function respondServerError(): JsonResponse
    {
        return response()->json([], HttpResponseCodes::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Return single error message
     *
     * @param string  $message      Error message.
     * @param integer $responseCode Resource code.
     * @return JsonResponse
     */
    public function respondError(
        string $message,
        int $responseCode = HttpResponseCodes::HTTP_UNPROCESSABLE_ENTITY
    ): JsonResponse {
        return response()->json([
            'message' => $message
        ], $responseCode);
    }

    /**
     * Return formatted errors
     *
     * @param array   $errors       Error messages.
     * @param integer $responseCode Resource code.
     * @return JsonResponse
     */
    public function respondWithErrors(
        array $errors,
        int $responseCode = HttpResponseCodes::HTTP_UNPROCESSABLE_ENTITY
    ): JsonResponse {
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
     * @param array|Model|Collection $data         Resource data.
     * @param array                  $options      Respond options.
     * @param callable|null          $validationFn Optional validation function to check the data before responding.
     * @return JsonResponse
     */
    protected function respondAsResource(
        mixed $data,
        array $options = [],
        $validationFn = null
    ): JsonResponse {
        $isCollection = ($options['isCollection'] ?? false);
        $appendData = ($options['appendData'] ?? null);
        $resourceName = ($options['resourceName'] ?? '');
        $transformResourceName = ($options['transformResourceName'] ?? true);
        $respondCode = ($options['respondCode'] ?? HttpResponseCodes::HTTP_OK);

        if ($data === null || ($data instanceof Collection && $data->count() === 0)) {
            $validationData = [];
            if (array_key_exists('appendData', $options) === true) {
                $validationData = $options['appendData'];
            }

            if ($validationFn === null || $validationFn($validationData) === true) {
                return $this->respondNotFound();
            }
        }

        if (empty($resourceName) === true) {
            $resourceName = $this->resourceName;
        }

        if (empty($resourceName) === true) {
            $resourceName = get_class($this);
            $resourceName = substr($resourceName, (strrpos($resourceName, '\\') + 1));
            $resourceName = substr($resourceName, 0, strpos($resourceName, 'Controller'));
            $resourceName = strtolower($resourceName);
        }

        $dataArray = [];
        if ($data instanceof Collection) {
            $dataArray = $data->toArray();
        } elseif (is_array($data) === true) {
            $dataArray = $data;
        } elseif ($data instanceof Model) {
            $dataArray = $data->toArray();
        }

        $resource = [];
        if ($isCollection === true) {
            $resource = [$transformResourceName === true ? Str::plural($resourceName) : $resourceName => $dataArray];
        } else {
            $resource = [$transformResourceName === true ? Str::singular($resourceName) : $resourceName => $dataArray];
        }

        if ($appendData !== null) {
            $resource += $appendData;
        }

        return response()->json($resource, $respondCode);
    }

    /**
     * Get the Controller Model Class name.
     *
     * @return string
     */
    public function getModelClass(): string
    {
        $controllerClass = static::class;

        $modelName = 'App\\Models\\' . Str::replaceLast('Controller', '', Str::afterLast($controllerClass, '\\'));

        if (class_exists($modelName) === false) {
            return $modelName;
        }

        return $modelName;
    }
}
