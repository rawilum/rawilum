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
use function count;

/**
 * Validate management entries token
 */
function validate_management_entries_token($token) : bool
{
    return Filesystem::has(PATH['site'] . '/tokens/management/entries/' . $token . '/token.yaml');
}

/**
 * Validate access token
 */
function validate_access_token($token) : bool
{
    return Filesystem::has(PATH['site'] . '/tokens/access/' . $token . '/token.yaml');
}

/**
 * Fetch entry(entries)
 *
 * endpoint: GET /api/management/entries
 *
 * Query:
 * id     - [REQUIRED] - Unique identifier of the entry(entries).
 * token  - [REQUIRED] - Valid Content Management API token for Entries.
 * filter - [OPTIONAL] - Select items in collection by given conditions.
 *
 * Returns:
 * An array of entry item objects.
 */
$app->get('/api/management/entries', function (Request $request, Response $response) use ($flextype) {
    // Get Query Params
    $query = $request->getQueryParams();

    // Set variables
    $id     = $query['id'];
    $token  = $query['token'];
    $filter = $query['filter'] ?? null;

    if ($flextype['registry']->get('flextype.settings.api.entries.enabled')) {
        // Validate management token
        if (validate_management_entries_token($token)) {
            $management_entries_token_file_path = PATH['site'] . '/tokens/management/entries/' . $token. '/token.yaml';

            // Set management token file
            if ($management_entries_token_file_data = $flextype['parser']->decode(Filesystem::read($management_entries_token_file_path), 'yaml')) {
                if ($management_entries_token_file_data['state'] === 'disabled' ||
                    ($management_entries_token_file_data['limit_calls'] !== 0 && $management_entries_token_file_data['calls'] >= $management_entries_token_file_data['limit_calls'])) {
                    return $response->withJson(['detail' => 'Incorrect authentication credentials.'], 401);
                }

                // Fetch entry
                $data['data'] = $flextype['entries']->fetch($id, $filter);

                // Set response code
                $response_code = count($data['data']) > 0 ? 200 : 404;

                // Update calls counter
                Filesystem::write($management_entries_token_file_path, $flextype['parser']->encode(array_replace_recursive($management_entries_token_file_data, ['calls' => $management_entries_token_file_data['calls'] + 1]), 'yaml'));

                // Return response
                return $response
                       ->withJson($data, $response_code)
                       ->withHeader('Access-Control-Allow-Origin', '*');
            }

            return $response
                   ->withJson(['detail' => 'Incorrect authentication credentials.'], 401)
                   ->withHeader('Access-Control-Allow-Origin', '*');
        }

        return $response
               ->withJson(['detail' => 'Incorrect authentication credentials.'], 401)
               ->withHeader('Access-Control-Allow-Origin', '*');
    }

    return $response
           ->withJson(['detail' => 'Incorrect authentication credentials.'], 401)
           ->withHeader('Access-Control-Allow-Origin', '*');
});


/**
 * Create entry
 *
 * endpoint: POST /api/management/entries
 *
 * Body:
 * id            - [REQUIRED] - Unique identifier of the entry.
 * token         - [REQUIRED] - Valid Content Management API token for Entries.
 * access_token  - [REQUIRED] - Valid Authentication token.
 * data          - [REQUIRED] - Data to store for the entry.
 *
 * Returns:
 * Returns the entry item object for the entry item that was just created.
 */
$app->post('/api/management/entries', function (Request $request, Response $response) use ($flextype) {

    // Get Post Data
    $post_data = $request->getParsedBody();

    // Set variables
    $token        = $post_data['token'];
    $access_token = $post_data['access_token'];
    $id           = $post_data['id'];
    $data         = $post_data['data'];

    if ($flextype['registry']->get('flextype.settings.api.entries.enabled')) {

        // Validate management and auth token
        if (validate_management_entries_token($token) && validate_access_token($access_token)) {
            $management_entries_token_file_path = PATH['site'] . '/tokens/management/entries/' . $token . '/token.yaml';
            $access_token_file_path = PATH['site'] . '/tokens/access/' . $access_token . '/token.yaml';

            // Set management and auth token file
            if (($management_entries_token_file_data = $flextype['parser']->decode(Filesystem::read($management_entries_token_file_path), 'yaml')) &&
                ($access_token_file_data = $flextype['parser']->decode(Filesystem::read($access_token_file_path), 'yaml'))) {

                if ($management_entries_token_file_data['state'] === 'disabled' ||
                    ($management_entries_token_file_data['limit_calls'] !== 0 && $management_entries_token_file_data['calls'] >= $management_entries_token_file_data['limit_calls'])) {
                    return $response->withJson(['detail' => 'Incorrect authentication credentials.0'], 401);
                }

                if ($access_token_file_data['state'] === 'disabled' ||
                    ($access_token_file_data['limit_calls'] !== 0 && $access_token_file_data['calls'] >= $access_token_file_data['limit_calls'])) {
                    return $response->withJson(['detail' => 'Incorrect authentication credentials.00'], 401);
                }

                // Create entry
                $create_entry = $flextype['entries']->create($id, $data);

                if ($create_entry) {
                    $data = $flextype['entries']->fetch($id);
                } else {
                    $data = [];
                }

                // Set response code
                $response_code = ($create_entry) ? 200 : 404;

                // Update calls counter
                Filesystem::write($management_entries_token_file_path, $flextype['parser']->encode(array_replace_recursive($management_entries_token_file_data, ['calls' => $management_entries_token_file_data['calls'] + 1]), 'yaml'));

                // Return response
                return $response
                       ->withJson($data, $response_code)
                       ->withHeader('Access-Control-Allow-Origin', '*');
            }

            return $response
                   ->withJson(['detail' => 'Incorrect authentication credentials.1'], 401)
                   ->withHeader('Access-Control-Allow-Origin', '*');
        }

        return $response
               ->withJson(['detail' => 'Incorrect authentication credentials.2'], 401)
               ->withHeader('Access-Control-Allow-Origin', '*');
    }

    return $response
           ->withJson(['detail' => 'Incorrect authentication credentials.3'], 401)
           ->withHeader('Access-Control-Allow-Origin', '*');
});

/**
 * Update entry
 *
 * endpoint: PATCH /api/management/entries
 *
 * Body:
 * id           - [REQUIRED] - Unique identifier of the entry.
 * token        - [REQUIRED] - Valid Content Management API token for Entries.
 * access_token - [REQUIRED] - Valid Authentication token.
 * data         - [REQUIRED] - Data to update for the entry.
 *
 * Returns:
 * Returns the entry item object for the entry item that was just created.
 */
$app->patch('/api/management/entries', function (Request $request, Response $response) use ($flextype) {

    // Get Post Data
    $post_data = $request->getParsedBody();

    // Set variables
    $token        = $post_data['token'];
    $access_token = $post_data['access_token'];
    $id           = $post_data['id'];
    $data         = $post_data['data'];

    if ($flextype['registry']->get('flextype.settings.api.entries.enabled')) {

        // Validate management and auth token
        if (validate_management_entries_token($token) && validate_access_token($access_token)) {
            $management_entries_token_file_path = PATH['site'] . '/tokens/management/entries/' . $token . '/token.yaml';
            $access_token_file_path = PATH['site'] . '/tokens/access/' . $access_token . '/token.yaml';

            // Set management and auth token file
            if (($management_entries_token_file_data = $flextype['parser']->decode(Filesystem::read($management_entries_token_file_path), 'yaml')) &&
                ($access_token_file_data = $flextype['parser']->decode(Filesystem::read($access_token_file_path), 'yaml'))) {

                if ($management_entries_token_file_data['state'] === 'disabled' ||
                    ($management_entries_token_file_data['limit_calls'] !== 0 && $management_entries_token_file_data['calls'] >= $management_entries_token_file_data['limit_calls'])) {
                    return $response->withJson(['detail' => 'Incorrect authentication credentials.0'], 401);
                }

                if ($access_token_file_data['state'] === 'disabled' ||
                    ($access_token_file_data['limit_calls'] !== 0 && $access_token_file_data['calls'] >= $access_token_file_data['limit_calls'])) {
                    return $response->withJson(['detail' => 'Incorrect authentication credentials.00'], 401);
                }

                // Update entry
                $update_entry = $flextype['entries']->update($id, $data);

                if ($update_entry) {
                    $data = $flextype['entries']->fetch($id);
                } else {
                    $data = [];
                }

                // Set response code
                $response_code = ($update_entry) ? 200 : 404;

                // Update calls counter
                Filesystem::write($management_entries_token_file_path, $flextype['parser']->encode(array_replace_recursive($management_entries_token_file_data, ['calls' => $management_entries_token_file_data['calls'] + 1]), 'yaml'));

                // Return response
                return $response
                       ->withJson($data, $response_code)
                       ->withHeader('Access-Control-Allow-Origin', '*');
            }

            return $response
                   ->withJson(['detail' => 'Incorrect authentication credentials.1'], 401)
                   ->withHeader('Access-Control-Allow-Origin', '*');
        }

        return $response
               ->withJson(['detail' => 'Incorrect authentication credentials.2'], 401)
               ->withHeader('Access-Control-Allow-Origin', '*');
    }

    return $response
           ->withJson(['detail' => 'Incorrect authentication credentials.3'], 401)
           ->withHeader('Access-Control-Allow-Origin', '*');
});

/**
 * Rename entry
 *
 * endpoint: PUT /api/management/entries
 *
 * Body:
 * id            - [REQUIRED] - Unique identifier of the entry.
 * new_id        - [REQUIRED] - New Unique identifier of the entry.
 * token         - [REQUIRED] - Valid Content Management API token for Entries.
 * access_token  - [REQUIRED] - Valid Authentication token.
 *
 * Returns:
 * Returns the entry item object for the entry item that was just renamed.
 */
$app->put('/api/management/entries', function (Request $request, Response $response) use ($flextype) {

    // Get Post Data
    $post_data = $request->getParsedBody();

    // Set variables
    $token         = $post_data['token'];
    $access_token  = $post_data['access_token'];
    $id            = $post_data['id'];
    $new_id        = $post_data['new_id'];

    if ($flextype['registry']->get('flextype.settings.api.entries.enabled')) {

        // Validate management and auth token
        if (validate_management_entries_token($token) && validate_access_token($access_token)) {
            $management_entries_token_file_path = PATH['site'] . '/tokens/management/entries/' . $token . '/token.yaml';
            $access_token_file_path = PATH['site'] . '/tokens/access/' . $access_token . '/token.yaml';

            // Set management and auth token file
            if (($management_entries_token_file_data = $flextype['parser']->decode(Filesystem::read($management_entries_token_file_path), 'yaml')) &&
                ($access_token_file_data = $flextype['parser']->decode(Filesystem::read($access_token_file_path), 'yaml'))) {

                if ($management_entries_token_file_data['state'] === 'disabled' ||
                    ($management_entries_token_file_data['limit_calls'] !== 0 && $management_entries_token_file_data['calls'] >= $management_entries_token_file_data['limit_calls'])) {
                    return $response->withJson(['detail' => 'Incorrect authentication credentials.0'], 401);
                }

                if ($access_token_file_data['state'] === 'disabled' ||
                    ($access_token_file_data['limit_calls'] !== 0 && $access_token_file_data['calls'] >= $access_token_file_data['limit_calls'])) {
                    return $response->withJson(['detail' => 'Incorrect authentication credentials.00'], 401);
                }

                // Rename entry
                $rename_entry = $flextype['entries']->rename($id, $new_id);

                // Get entry data
                if ($rename_entry) {
                    $data = $flextype['entries']->fetch($new_id);
                } else {
                    $data = [];
                }

                // Set response code
                $response_code = ($data) ? 200 : 404;

                // Update calls counter
                Filesystem::write($management_entries_token_file_path, $flextype['parser']->encode(array_replace_recursive($management_entries_token_file_data, ['calls' => $management_entries_token_file_data['calls'] + 1]), 'yaml'));

                // Return response
                return $response
                       ->withJson($data, $response_code)
                       ->withHeader('Access-Control-Allow-Origin', '*');
            }

            return $response
                   ->withJson(['detail' => 'Incorrect authentication credentials.1'], 401)
                   ->withHeader('Access-Control-Allow-Origin', '*');
        }

        return $response
               ->withJson(['detail' => 'Incorrect authentication credentials.2'], 401)
               ->withHeader('Access-Control-Allow-Origin', '*');
    }

    return $response
           ->withJson(['detail' => 'Incorrect authentication credentials.3'], 401)
           ->withHeader('Access-Control-Allow-Origin', '*');
});

/**
 * Copy entry(entries)
 *
 * endpoint: PUT /api/management/entries/copy
 *
 * Body:
 * id            - [REQUIRED] - Unique identifier of the entry.
 * new_id        - [REQUIRED] - New Unique identifier of the entry.
 * token         - [REQUIRED] - Valid Content Management API token for Entries.
 * access_token  - [REQUIRED] - Valid Authentication token.
 *
 * Returns:
 * Returns the entry item object for the entry item that was just copied.
 */
$app->put('/api/management/entries/copy', function (Request $request, Response $response) use ($flextype) {

    // Get Post Data
    $data = $request->getParsedBody();

    // Set variables
    $token        = $post_data['token'];
    $access_token = $post_data['access_token'];
    $id           = $post_data['id'];
    $new_id       = $post_data['new_id'];

    if ($flextype['registry']->get('flextype.settings.api.entries.enabled')) {

        // Validate management and auth token
        if (validate_management_entries_token($token) && validate_access_token($access_token)) {
            $management_entries_token_file_path = PATH['site'] . '/tokens/management/entries/' . $token . '/token.yaml';
            $access_token_file_path = PATH['site'] . '/tokens/access/' . $access_token . '/token.yaml';

            // Set management and auth token file
            if (($management_entries_token_file_data = $flextype['parser']->decode(Filesystem::read($management_entries_token_file_path), 'yaml')) &&
                ($access_token_file_data = $flextype['parser']->decode(Filesystem::read($access_token_file_path), 'yaml'))) {

                if ($management_entries_token_file_data['state'] === 'disabled' ||
                    ($management_entries_token_file_data['limit_calls'] !== 0 && $management_entries_token_file_data['calls'] >= $management_entries_token_file_data['limit_calls'])) {
                    return $response->withJson(['detail' => 'Incorrect authentication credentials.0'], 401);
                }

                if ($access_token_file_data['state'] === 'disabled' ||
                    ($access_token_file_data['limit_calls'] !== 0 && $access_token_file_data['calls'] >= $access_token_file_data['limit_calls'])) {
                    return $response->withJson(['detail' => 'Incorrect authentication credentials.00'], 401);
                }

                // Rename entry
                $rename_entry = $flextype['entries']->copy($id, $new_id);

                // Get entry data
                if ($rename_entry) {
                    $data = $flextype['entries']->fetch($new_id);
                } else {
                    $data = [];
                }

                // Set response code
                $response_code = ($data) ? 200 : 404;

                // Update calls counter
                Filesystem::write($management_entries_token_file_path, $flextype['parser']->encode(array_replace_recursive($management_entries_token_file_data, ['calls' => $management_entries_token_file_data['calls'] + 1]), 'yaml'));

                // Return response
                return $response
                       ->withJson($data, $response_code)
                       ->withHeader('Access-Control-Allow-Origin', '*');
            }

            return $response
                   ->withJson(['detail' => 'Incorrect authentication credentials.1'], 401)
                   ->withHeader('Access-Control-Allow-Origin', '*');
        }

        return $response
               ->withJson(['detail' => 'Incorrect authentication credentials.2'], 401)
               ->withHeader('Access-Control-Allow-Origin', '*');
    }

    return $response
           ->withJson(['detail' => 'Incorrect authentication credentials.3'], 401)
           ->withHeader('Access-Control-Allow-Origin', '*');
});

/**
 * Delete entry
 *
 * endpoint: DELETE /api/management/entries
 *
 * Body:
 * id           - [REQUIRED] - Unique identifier of the entry.
 * token        - [REQUIRED] - Valid Content Management API token for Entries.
 * access_token - [REQUIRED] - Valid Authentication token.
 *
 * Returns:
 * Returns an empty body with HTTP status 204
 */
$app->delete('/api/management/entries', function (Request $request, Response $response) use ($flextype) {

    // Get Post Data
    $post_data = $request->getParsedBody();

    // Set variables
    $token        = $post_data['token'];
    $access_token = $post_data['access_token'];
    $id           = $post_data['id'];

    if ($flextype['registry']->get('flextype.settings.api.entries.enabled')) {

        // Validate management and auth token
        if (validate_management_entries_token($token) && validate_access_token($access_token)) {
            $management_entries_token_file_path = PATH['site'] . '/tokens/management/entries/' . $token . '/token.yaml';
            $access_token_file_path = PATH['site'] . '/tokens/access/' . $access_token . '/token.yaml';

            // Set management and auth token file
            if (($management_entries_token_file_data = $flextype['parser']->decode(Filesystem::read($management_entries_token_file_path), 'yaml')) &&
                ($access_token_file_data = $flextype['parser']->decode(Filesystem::read($access_token_file_path), 'yaml'))) {

                if ($management_entries_token_file_data['state'] === 'disabled' ||
                    ($management_entries_token_file_data['limit_calls'] !== 0 && $management_entries_token_file_data['calls'] >= $management_entries_token_file_data['limit_calls'])) {
                    return $response->withJson(['detail' => 'Incorrect authentication credentials.0'], 401);
                }

                if ($access_token_file_data['state'] === 'disabled' ||
                    ($access_token_file_data['limit_calls'] !== 0 && $access_token_file_data['calls'] >= $access_token_file_data['limit_calls'])) {
                    return $response->withJson(['detail' => 'Incorrect authentication credentials.00'], 401);
                }

                // Delete entry
                $data = $flextype['entries']->delete($id);

                // Set response code
                $response_code = ($data) ? 204 : 404;

                // Update calls counter
                Filesystem::write($management_entries_token_file_path, $flextype['parser']->encode(array_replace_recursive($management_entries_token_file_data, ['calls' => $management_entries_token_file_data['calls'] + 1]), 'yaml'));

                // Return response
                return $response
                       ->withJson($data, $response_code)
                       ->withHeader('Access-Control-Allow-Origin', '*');
            }

            return $response
                   ->withJson(['detail' => 'Incorrect authentication credentials.1'], 401)
                   ->withHeader('Access-Control-Allow-Origin', '*');
        }

        return $response
               ->withJson(['detail' => 'Incorrect authentication credentials.2'], 401)
               ->withHeader('Access-Control-Allow-Origin', '*');
    }

    return $response
           ->withJson(['detail' => 'Incorrect authentication credentials.3'], 401)
           ->withHeader('Access-Control-Allow-Origin', '*');
});
