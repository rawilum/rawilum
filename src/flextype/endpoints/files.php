<?php

declare(strict_types=1);

/**
 * Flextype (http://flextype.org)
 * Founded by Sergey Romanenko and maintained by Flextype Community.
 */

namespace Flextype;

use Flextype\Component\Filesystem\Filesystem;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use function array_replace_recursive;

/**
 * Validate files token
 */
function validate_files_token($token) : bool
{
    return Filesystem::has(PATH['project'] . '/tokens/files/' . $token . '/token.yaml');
}

/**
 * Fetch file(s)
 *
 * endpoint: GET /api/files
 *
 * Query:
 * path   - [REQUIRED] - Unique identifier of the file path.
 * token  - [REQUIRED] - Valid Files token.
 *
 * Returns:
 * An array of entry item objects.
 */
$app->get('/api/files', function (Request $request, Response $response) use ($flextype, $api_sys_messages) {

    // Get Query Params
    $query = $request->getQueryParams();

    // Set variables
    $path  = $query['path'];
    $token = $query['token'];

    if ($flextype['registry']->get('flextype.settings.api.files.enabled')) {

        // Validate delivery token
        if (validate_files_token($token)) {
            $delivery_files_token_file_path = PATH['project'] . '/tokens/files/' . $token. '/token.yaml';

            // Set delivery token file
            if ($delivery_files_token_file_data = $flextype['serializer']->decode(Filesystem::read($delivery_files_token_file_path), 'yaml')) {
                if ($delivery_files_token_file_data['state'] === 'disabled' ||
                    ($delivery_files_token_file_data['limit_calls'] !== 0 && $delivery_files_token_file_data['calls'] >= $delivery_files_token_file_data['limit_calls'])) {
                    return $response->withJson($api_sys_messages['AccessTokenInvalid'], 401);
                }

                // Create files array
                $files = [];

                // Get list if file or files for specific folder
                if (is_dir($path)) {
                    $files = $flextype['media_files']->fetchCollection($path);
                } else {
                    $files = $flextype['media_files']->fetchSingle($path);
                }

                // Write response data
                $response_data['data'] = $files;

                // Set response code
                $response_code = count($response_data['data']) > 0 ? 200 : 404;

                // Update calls counter
                Filesystem::write($delivery_files_token_file_path, $flextype['serializer']->encode(array_replace_recursive($delivery_files_token_file_data, ['calls' => $delivery_files_token_file_data['calls'] + 1]), 'yaml'));

                if ($response_code == 404) {

                    // Return response
                    return $response
                           ->withJson($api_sys_messages['NotFound'], $response_code);
                }

                // Return response
                return $response
                       ->withJson($response_data, $response_code);
            }

            return $response
                   ->withJson($api_sys_messages['AccessTokenInvalid'], 401);
        }

        return $response
               ->withJson($api_sys_messages['AccessTokenInvalid'], 401);
    }

    return $response
           ->withJson($api_sys_messages['AccessTokenInvalid'], 401);
});

/**
 * Create a media file
 *
 * endpoint: POST /api/files
 *
 * Body:
 * folder        - [REQUIRED] - The folder you're targetting.
 * token         - [REQUIRED] - Valid Entries token.
 * access_token  - [REQUIRED] - Valid Access token.
 * file          - [REQUIRED] - Raw file data (multipart/form-data).
 *
 * Returns:
 * Returns the entry item object for the entry item that was just created.
 */
$app->post('/api/files', function (Request $request, Response $response) use ($flextype, $api_sys_messages) {

    // Get Post Data
    $post_data = $request->getParsedBody();

    // Set variables
    $token        = $post_data['token'];
    $access_token = $post_data['access_token'];
    $folder       = $post_data['folder'];
    $file         = $_FILES['file'];

    if ($flextype['registry']->get('flextype.settings.api.files.enabled')) {

        // Validate files and access token
        if (validate_files_token($token) && validate_access_token($access_token)) {
            $files_token_file_path = PATH['project'] . '/tokens/files/' . $token . '/token.yaml';
            $access_token_file_path = PATH['project'] . '/tokens/access/' . $access_token . '/token.yaml';

            // Set files and access token file
            if (($files_token_file_data = $flextype['serializer']->decode(Filesystem::read($files_token_file_path), 'yaml')) &&
                ($access_token_file_data = $flextype['serializer']->decode(Filesystem::read($access_token_file_path), 'yaml'))) {

                if ($files_token_file_data['state'] === 'disabled' ||
                    ($files_token_file_data['limit_calls'] !== 0 && $files_token_file_data['calls'] >= $files_token_file_data['limit_calls'])) {
                    return $response->withJson($api_sys_messages['AccessTokenInvalid'], 401);
                }

                if ($access_token_file_data['state'] === 'disabled' ||
                    ($access_token_file_data['limit_calls'] !== 0 && $access_token_file_data['calls'] >= $access_token_file_data['limit_calls'])) {
                    return $response->withJson($api_sys_messages['AccessTokenInvalid'], 401);
                }

                // Create entry
                $create_file = $flextype['media_files']->create($file, $folder);

                if ($create_file) {
                    $response_data['data'] = $flextype['media_files']->fetch($folder . '/' . basename($create_file));
                } else {
                    $response_data['data'] = [];
                }

                // Set response code
                $response_code = (Filesystem::has($create_file)) ? 200 : 404;

                // Return response
                return $response
                       ->withJson($response_data, $response_code);

                // Update calls counter
                Filesystem::write($files_token_file_path, $flextype['serializer']->encode(array_replace_recursive($files_token_file_data, ['calls' => $files_token_file_data['calls'] + 1]), 'yaml'));

                if ($response_code == 404) {

                    // Return response
                    return $response
                           ->withJson($api_sys_messages['NotFound'], $response_code);
                }

                // Return response
                return $response
                       ->withJson($response_data, $response_code);
            }

            return $response
                   ->withJson($api_sys_messages['AccessTokenInvalid'], 401);
        }

        return $response
               ->withJson($api_sys_messages['AccessTokenInvalid'], 401);
    }

    return $response
           ->withJson($api_sys_messages['AccessTokenInvalid'], 401);
});
