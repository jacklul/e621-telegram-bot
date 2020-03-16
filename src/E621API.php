<?php
/**
 * This file is part of the e621 Search Lite project.
 *
 * (c) Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace jacklul\e621bot;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException as GuzzleConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

class E621API
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $default_options = [
            'base_uri' => 'https://e621.net',
        ];

        $this->client = new Client(array_replace_recursive($default_options, $options));
    }

    /**
     * @param array $data
     *
     * @return array|mixed
     */
    public function posts(array $data = [])
    {
        if (isset($data['tags'])) {
            $tags = trim(preg_replace('!\s+!', ' ', $data['tags']));

            if (substr_count($tags, ' ') + 1 > 6) {
                return ['reason' => 'You can only search up to 6 tags.'];
            }
        }

        try {
            $response = $this->client->request('GET', 'posts.json', ['query' => $data]);
            $result = json_decode((string)$response->getBody(), true);

            if (!is_array($result)) {
                $result = [
                    'reason' => 'Data received from e621.net API is invalid',
                    'error'  => 'Response couldn\'t be decoded into array',
                ];
            }

            return ['result' => $result];
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * @param Exception $e
     *
     * @return array
     */
    private function handleException(Exception $e)
    {
        if ($e instanceof GuzzleException) {
            return [
                'reason' => 'HTTP Client error',
                'error'  => $e->getMessage(),
            ];
        }

        if ($e instanceof GuzzleConnectException) {
            return [
                'reason' => 'Connection to e621.net API failed or timed out',
                'error'  => $e->getMessage(),
            ];
        }

        if ($e instanceof RequestException) {
            $response = $e->getResponse();

            if ($response !== null) {
                if ($response->getBody() !== null) {
                    $result = json_decode((string)$response->getBody(), true);

                    if (is_array($result)) {
                        return ['result' => $result];
                    }
                }

                if ($response->getStatusCode() === 403) {
                    return [
                        'reason' => 'Authorization required for this request',
                        'error'  => $e->getResponse(),
                    ];
                }

                return [
                    'reason' => 'Connection to e621.net API failed or timed out',
                    'error'  => 'Request resulted in a `' . $response->getStatusCode() . ' ' . $response->getReasonPhrase() . '` response',
                ];
            }

            return [
                'reason' => 'HTTP Request error',
                'error'  => $e->getResponse(),
            ];
        }

        return [
            'reason' => 'Unknown error',
            'error'  => $e->getMessage(),
        ];
    }
}
