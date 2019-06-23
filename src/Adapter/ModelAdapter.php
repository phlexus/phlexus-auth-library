<?php declare(strict_types=1);

namespace Phlexus\Libraries\Auth\Adapter;

use Phalcon\DiInterface;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Security;
use Phlexus\Libraries\Auth\Manager;

/**
 * Auth Model Adapter
 *
 * Make auth with Phalcon Model
 *
 * @package Phlexus\Libraries\Auth\Adapter
 */
class ModelAdapter extends AbstractAdapter
{
    /**
     * Identity conditions string key
     */
    const IDENTITY_KEY = 'identity';

    /**
     * @var string
     */
    protected $modelClass;

    /**
     * @var string
     */
    protected $identityField;

    /**
     * @var string
     */
    protected $passwordField;

    /**
     * @var string
     */
    protected $userIdField;

    /**
     * @var ModelInterface|null
     */
    protected $user;

    /**
     * @var DiInterface
     */
    protected $di;

    /**
     * Model constructor.
     *
     * @param array $configurations
     * @param DiInterface $di
     * @throws AuthAdapterException
     */
    public function __construct(DiInterface $di, array $configurations)
    {
        $fields = $configurations['fields'];

        $this->modelClass = $configurations['model'];
        $this->identityField = $fields[self::IDENTITY_KEY];
        $this->passwordField = $fields['password'];
        $this->userIdField = $fields['id'];

        $this->di = $di;

        if (!class_exists($this->modelClass)) {
            throw new AuthAdapterException('Model class do not exists. In ' . __CLASS__);
        }

        if (!$this->di->has('security')) {
            throw new AuthAdapterException('Security component as service provider is required. In' . __CLASS__);
        }
    }

    /**
     * @param array $credentials
     * @return bool
     */
    public function login(array $credentials = []): bool
    {
        /** @var \Phalcon\Mvc\Model $class */
        $class = new $this->modelClass;
        $primaryKey = $this->userIdField;

        $row = $class::findFirst([
            'columns' => [$primaryKey],
            sprintf('%s = ?%s', $this->identityField, self::IDENTITY_KEY),
            'bind' => [
                self::IDENTITY_KEY => $credentials[self::IDENTITY_KEY],
            ],
        ]);

        if (!$row instanceof ModelInterface) {
            return false;
        }

        /** @var Security $security */
        $security = $this->di->getShared('security');
        if (!$security->checkHash($credentials['password'], $row->readAttribute($this->passwordField))) {
            return false;
        }

        $this->user = $row;
        $this->di->getShared('session')->set(Manager::SESSION_AUTH_KEY, $row->$primaryKey);

        return true;
    }

    /**
     * @return bool
     */
    public function logout(): bool
    {
        $session = $this->di->getShared('session');
        $session->remove(Manager::SESSION_AUTH_KEY);

        return true;
    }

    /**
     * @return bool
     */
    public function isLogged(): bool
    {
        return $this->di->getShared('session')->has(Manager::SESSION_AUTH_KEY);
    }

    /**
     * Get value identity
     *
     * @return mixed
     */
    public function getIdentity()
    {
        $primaryKey = $this->userIdField;
        if ($this->user instanceof ModelInterface) {
            return $this->user->$primaryKey;
        }

        return $this->di->getShared('session')->get(Manager::SESSION_AUTH_KEY);
    }
}