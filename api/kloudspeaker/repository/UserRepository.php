<?php
namespace Kloudspeaker\Repository;

use \Kloudspeaker\Database\Database as Database;

class UserRepository {
    public function __construct($container) {
        $this->logger = $container->logger;
        $this->db = $container->db;
    }

    //private function toUser($props) {
    //    return new \Kloudspeaker\Model\User($props['id'], $props['name'], $props['user_type'], $props['lang'], $props['email'], $props['auth']);
    //}

    public function find($name, $allowEmail = FALSE, $expiration = FALSE) {
        $cols = ['id', 'name', 'lower(user_type) as user_type', 'lower(lang) as lang', 'email', 'lower(user_auth.type) as auth, expiration'];

        $q = $this->db->select('user', $cols)->types(["expiration" => Database::TYPE_DATETIME_INTERNAL, "is_group" => Database::TYPE_INT])->leftJoin('user_auth', 'user.id = user_auth.user_id');
        $w = $q->where('is_group', 0);

        if ($expiration)
            $w->andWhere('expiration', $expiration, '>')->orIsNull('expiration');

        if ($allowEmail)
            $w->andWhere('name', $name)->or('email', $name);
        else
            $w->andWhere('name', $name);

        $result = $q->execute();
        $matches = $result->count();

        if ($matches === 0) {
            $this->logger->error("No user found with name", ["name" => $name]);
            return NULL;
        }

        if ($matches > 1) {
            $this->logger->error("Duplicate user found with name", ["name" => $name]);
            return NULL;
        }

        return $result->firstRow();
    }

    public function get($id, $expiration = FALSE) {
        $cols = ['id', 'name', 'lower(user_type) as user_type', 'lower(lang) as lang', 'email', 'lower(user_auth.type) as auth, expiration'];

        $q = $this->db->select('user', $cols)->types(["expiration" => Database::TYPE_DATETIME_INTERNAL, "is_group" => Database::TYPE_INT])->leftJoin('user_auth', 'user.id = user_auth.user_id');
        //TODO boolean support
        $w = $q->where('is_group', 0)->and('id', $id);

        if ($expiration)
            $w->andWhere('expiration', $expiration, '>')->orIsNull('expiration');

        $result = $q->execute();
        $matches = $result->count();

        if ($matches === 0) {
            $this->logger->error("No user found with id", ["id" => $id]);
            return NULL;
        }

        return $result->firstRow();
    }

    public function getUserAuth($userId) {
        $this->logger->debug("get auth ".$userId);

        return $this->db->select('user_auth', ['user_id', 'lower(type) as type', 'hash', 'salt', 'hint'])->where('user_id', $userId)->execute()->firstRow();
    }
}