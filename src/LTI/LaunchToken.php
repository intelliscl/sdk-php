<?php

namespace Intellischool\LTI;

use Firebase\JWT\JWT;
use Intellischool\IntelliSchoolException;

class LaunchToken
{
    const LTI_CLAIM_ROLES = 'https://purl.imsglobal.org/spec/lti/claim/roles';
    const LTI_CLAIM_DEPLOYMENT_ID = 'https://purl.imsglobal.org/spec/lti/claim/deployment_id';
    const LTI_CLAIM_MESSAGE_TYPE = 'https://purl.imsglobal.org/spec/lti/claim/message_type';
    const LTI_CLAIM_VERSION = 'https://purl.imsglobal.org/spec/lti/claim/version';
    const LTI_CLAIM_RESOURCE_LINK = 'https://purl.imsglobal.org/spec/lti/claim/resource_link';
    const LTI_CLAIM_TARGET_LINK_URI = 'https://purl.imsglobal.org/spec/lti/claim/target_link_uri';
    const LTI_CLAIM_LIS = 'https://purl.imsglobal.org/spec/lti/claim/lis';
    const LTI_CLAIM_LAUNCH_PRESENTATION = 'https://purl.imsglobal.org/spec/lti/claim/launch_presentation';

    const REQUIRED_CLAIMS = [
        'iss',
        'sub',
        'aud',
        'iat',
        'exp',
        'name',
        'given_name',
        'family_name',
        'email',
        self::LTI_CLAIM_DEPLOYMENT_ID,
        self::LTI_CLAIM_MESSAGE_TYPE,
        self::LTI_CLAIM_VERSION,
        self::LTI_CLAIM_RESOURCE_LINK,
        self::LTI_CLAIM_TARGET_LINK_URI,
    ];
    const LTI_CLAIM_ROLE_SCOPE_MENTOR = 'https://purl.imsglobal.org/lti/claim/role_scope_mentor';

    private array $payload = array(
        self::LTI_CLAIM_MESSAGE_TYPE => 'LtiResourceLinkRequest',
        self::LTI_CLAIM_VERSION => '1.3.0',
        'aud' => 'https://core.intellischool.net/auth/lti'
    );

    /**
     * @param string $value a unique URI for the platform that issued the token
     *
     * @return self
     */
    public function setIssuer(string $value): self
    {
        $this->payload['iss'] = $value;
        return $this;
    }

    /**
     * @param string $value a unique identifier for the entity for whom this token has been issued (see the Subject claims section below)
     *
     * @return self
     */
    public function setSubject(string $value): self
    {
        $this->payload['sub'] = $value;
        return $this;
    }

    /**
     * @param string $value the audience that the token has been issued to (always https://core.intellischool.net/auth/lti)
     *
     * @return self
     */
    public function setAudience(string $value): self
    {
        $this->payload['aud'] = $value;
        return $this;
    }

    /**
     * @param int|\DateTimeInterface $value the Unix epoch timestamp at which the token was created
     *
     * @return self
     */
    public function setIssuedAt($value): self
    {
        if ($value instanceof \DateTimeInterface)
        {
            $value = $value->getTimestamp();
        } else if (!is_numeric($value)) {
            throw new \InvalidArgumentException('$value must be an integer or DateTime instance');
        }
        $this->payload['iat'] = (int)$value;
        return $this;
    }

    /**
     * @param int|\DateTimeInterface $value the Unix epoch timestamp at which this token should be considered invalid (keep this to a minimum, preferably no more than 5-10 minutes)
     *
     * @return self
     */
    public function setExpiry($value): self
    {
        if ($value instanceof \DateTimeInterface)
        {
            $value = $value->getTimestamp();
        } else if (!is_numeric($value)) {
            throw new \InvalidArgumentException('$value must be an integer or DateTime instance');
        }
        $this->payload['exp'] = $value;
        return $this;
    }

    /**
     * @param string $value the display name of the user being authenticated
     *
     * @return self
     */
    public function setName(string $value): self
    {
        $this->payload['name'] = $value;
        return $this;
    }

    /**
     * @param string $value the family name of the user being authenticated
     *
     * @return self
     */
    public function setFamilyName(string $value): self
    {
        $this->payload['family_name'] = $value;
        return $this;
    }

    /**
     * @param string $value the given name of the user being authenticated
     *
     * @return self
     */
    public function setGivenName(string $value): self
    {
        $this->payload['given_name'] = $value;
        return $this;
    }

    /**
     * @param string $value the e-mail address of the user being authenticated
     *
     * @return self
     */
    public function setEmail(string $value): self
    {
        $this->payload['email'] = $value;
        return $this;
    }

    /**
     * @param string $value the unique deployment_id, which is provided as part of the LTI configuration in the IDaP
     *
     * @return self
     */
    public function setDeploymentId(string $value): self
    {
        $this->payload[self::LTI_CLAIM_DEPLOYMENT_ID] = $value;
        return $this;
    }

    /**
     * @param string $value the message type (always LtiResourceLinkRequest)
     *
     * @return self
     */
    public function setMessageType(string $value): self
    {
        $this->payload[self::LTI_CLAIM_MESSAGE_TYPE] = $value;
        return $this;
    }

    /**
     * @param string $value the LTI version in use (always 1.3.0)
     *
     * @return self
     */
    public function setVersion(string $value): self
    {
        $this->payload[self::LTI_CLAIM_VERSION] = $value;
        return $this;
    }

    /**
     * @param string $value an object containing the contextually unique ID (with relation to the deployment_id) of the resource being linked to
     *
     * @return self
     */
    public function setResourceLink(string $value): self
    {
        $this->payload[self::LTI_CLAIM_RESOURCE_LINK] = $value;
        return $this;
    }

    /**
     * @param string $value the URL of the Intellischool resource being linked to.
     *
     * @return self
     */
    public function setTargetLinkUri(string $value): self
    {
        $this->payload[self::LTI_CLAIM_TARGET_LINK_URI] = $value;
        return $this;
    }

    /**
     * @param string $value the middle name(s) of the user being authenticated
     *
     * @return self
     */
    public function setMiddleName(string $value): self
    {
        $this->payload['middle_name'] = $value;
        return $this;
    }

    /**
     * @param string $value a URL to the avatar/picture of the user being authenticated
     *
     * @return self
     */
    public function setPicture(string $value): self
    {
        $this->payload['picture'] = $value;
        return $this;
    }

    /**
     * @param string $value an ISO code representing the locality settings for the user
     *
     * @return self
     */
    public function setLocale(string $value): self
    {
        $this->payload['locale'] = $value;
        return $this;
    }

    /**
     * @param string[] $roles an array of roles applicable to the user being authenticated (must be an in the LTI role vocab list - more information on roles)
     *
     * @return self
     */
    public function setRole(...$roles): self
    {
        $this->payload[self::LTI_CLAIM_ROLES] = $roles;
        return $this;
    }

    /**
     * @param array|\stdClass $value an object containing Learning Information Services variables (if supplied, must include the person_sourcedId value)
     *
     * @return self
     */
    public function setLISVariables($value): self
    {
        $this->payload[self::LTI_CLAIM_LIS] = $value;
        return $this;
    }

    /**
     * @param string[] $value an array of students to which this user has a mentor role (required for situations where LTI is being used to authenticate a parent or caregiver)
     *
     * @return self
     */
    public function setMentor(array $value): self
    {
        $this->payload[self::LTI_CLAIM_ROLE_SCOPE_MENTOR] = $value;
        return $this;
    }

    /**
     * @param string  $document_target - the context in which the launched resource will be presented (must be one of either iframe, frame or window, defaults to iframe)
     * @param ?string $return_url      - the URL that the user should be redirected to upon completion of their session with the launched resource (defaults to null).
     *
     * @return self
     */
    public function setLaunchPresentation(string $document_target, ?string $return_url = null): self
    {
        $this->payload[self::LTI_CLAIM_LAUNCH_PRESENTATION] = ['document_target' => $document_target];
        if (!empty($return_url))
        {
            $this->payload[self::LTI_CLAIM_LAUNCH_PRESENTATION]['return_url'] = $return_url;
        }
        return $this;
    }

    /**
     * Verify the required fields and serialise the token to a JWT
     *
     * @param \OpenSSLAsymmetricKey|\OpenSSLCertificate|array|string $privateKey a private key acceptable by openssl_sign()
     *
     * @return string
     */
    public function build($privateKey): string
    {
        if (empty($this->payload['name']) && !empty($this->payload['given_name']) && !empty($this->payload['family_name']))
        {
            $this->payload['name'] = $this->payload['given_name'] . ' ' . $this->payload['family_name'];
        }
        if (empty($this->payload['iat']))
        {
            $this->setIssuedAt(time());
        }
        if (empty($this->payload['exp']))
        {
            $this->setExpiry($this->payload['iat'] + 300);
        }
        $missingKeys = [];
        foreach (self::REQUIRED_CLAIMS as $REQUIRED_CLAIM)
        {
            if (empty($this->payload[$REQUIRED_CLAIM]))
            {
                $missingKeys[] = $REQUIRED_CLAIM;
            }
        }
        if (!empty($missingKeys))
        {
            throw new IntelliSchoolException('Missing Launch Token claim(s): ' . implode(', ', $missingKeys));
        }
        return JWT::encode($this->payload, $privateKey, 'RS256');
    }
}
