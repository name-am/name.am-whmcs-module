<?php

namespace WHMCS\Module\Registrar\NameamApi;

/**
 * Name.am Registrar Module Name.am API Client.
 *
 * A Name.am API Client for communicating with an external API endpoint.
 */
class ApiClient
{
    const API_URL = 'https://api.name.am';

    protected $results = array();

    /**
     * Make external API call to registrar API.
     *
     * @param string $action
     * @param array $postfields
     *
     * @throws \Exception Connection error
     * @throws \Exception Bad API response
     *
     * @return array
     */
    public function call($action, $postfields, $request_method = "POST")
    {

        $response = [];
        // print_r(self::API_URL . $action);
        // exit;

        // if ($request_method == "POST") {
            // authentication
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => self::API_URL . '/auth/login',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => $postfields['authentication'],
                CURLOPT_HTTPHEADER => array("Content-Type: application/json"),
                CURLOPT_COOKIEJAR => dirname(__FILE__) . '/cookie.txt',
                CURLOPT_COOKIEJAR => 'cookie.txt',
            ));

            $authentication_response = curl_exec($ch);
            setcookie( "nameam_data", $authentication_response, strtotime( '+30 days' ) ); 

            // $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => self::API_URL . $action,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 100,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => $request_method,
                CURLOPT_POSTFIELDS => $postfields['post_data'],
                CURLOPT_COOKIEJAR => 'cookie.txt',
            ));
            $response = curl_exec($ch);
        // }



        if (curl_errno($ch)) {
            throw new \Exception('Connection Error: ' . curl_errno($ch) . ' - ' . curl_error($ch));
        }
        curl_close($ch);

        $this->results = $this->processResponse($response);
        /*print_r($authentication_response);
        echo "                                              <br><br>\n\t             ";
        print_r($response);
        // echo "\n\t";*/
        // print_r($action);
        // print_r(json_encode($postfields['post_data']));
        // echo "\n\t";
        // print_r($this->results);
        // print_r($response);
        // exit;

        logModuleCall(
            'Registrarmodule',
            $action,
            $postfields,
            $response,
            $this->results,
            array(
                $postfields['username'], // Mask username & password in request/response data
                $postfields['password'],
                $postfields['authentication'],
            )
        );

        if ($this->results['message']) {
            throw new \Exception($this->results['message']);
        }
        
        if ($this->results === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Bad response received from API');
        }


        return $this->results;
    }

    /**
     * Process API response.
     *
     * @param string $response
     *
     * @return array
     */
    public function processResponse($response)
    {
        return json_decode($response, true);
    }

    /**
     * Get from response results.
     *
     * @param string $key
     *
     * @return string
     */
    public function getFromResponse($key)
    {
        return isset($this->results[$key]) ? $this->results[$key] : '';
    }
}
