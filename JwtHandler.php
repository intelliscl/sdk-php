<?php
require './src/JWT/JWT.php';
require './src/JWT/ExpiredException.php';
require './src/JWT/SignatureInvalidException.php';
require './src/JWT/BeforeValidException.php';

use \IntelliSchool\JWT\JWT;

class JwtHandler
{
    protected $jwt_secret;
    protected $payload;
    protected $issuedAt;
    protected $expire;
    protected $algo;

    public function __construct()
    {
        // set your default time-zone
        date_default_timezone_set('Asia/Dhaka');

        $this->issuedAt = time();

        // Token Validity (3600 second = 1hr)
        $this->expire = $this->issuedAt + 3600;

        // Define Default algo
        $this->algo = 'RS256';

         // Assign Private Key
        $this->jwt_secret = file_get_contents('private_key.pem');
    }

    // ENCODING THE PAYLOAD
    public function _jwt_encode_data($iss, $data)
    {
        $this->payload = [
            //Adding the identifier to the Payload (who issue the token)
            "iss" => $iss,
            "aud" => $iss,
            // Adding the current timestamp to the Payload, for identifying that when the token was issued.
            "iat" => $this->issuedAt,
            // Token expiration
            "exp" => $this->expire,
            // Payload
            "data" => $data
        ];

        return JWT::encode($this->payload, $this->jwt_secret, $this->algo);
    }

    //DECODING THE TOKEN
    public function _jwt_decode_data($jwt_token)
    {
        // Parse private key
        $openssl_private_key = openssl_pkey_get_private($this->jwt_secret);
        if ($openssl_private_key === false) {
            var_dump(openssl_error_string());
        } else {
            // Extract Public Key from Private key
            $key_details = openssl_pkey_get_details($openssl_private_key);

            // Assign Public key
            $this->jwt_secret = $key_details['key'];
        }

        try {
            $decode = JWT::decode($jwt_token, $this->jwt_secret, [$this->algo]);
            return $decode->data;
        } catch (\IntelliSchool\JWT\ExpiredException $e) {
            return $e->getMessage();
        } catch (\IntelliSchool\JWT\SignatureInvalidException $e) {
            return $e->getMessage();
        } catch (\IntelliSchool\JWT\BeforeValidException $e) {
            return $e->getMessage();
        } catch (\DomainException $e) {
            return $e->getMessage();
        } catch (\InvalidArgumentException $e) {
            return $e->getMessage();
        } catch (\UnexpectedValueException $e) {
            return $e->getMessage();
        }
    }
}
