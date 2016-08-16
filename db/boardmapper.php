<?php

namespace OCA\Deck\Db;

use OCP\IDb;
use OCP\AppFramework\Db\Mapper;
use Symfony\Component\Config\Definition\Exception\Exception;


class BoardMapper extends Mapper {

    private $labelMapper;
    private $_relationMappers = array();

    public function addRelationMapper($name, $mapper) {
        $this->_relationMappers[$name] = $mapper;
    }

    public function __construct(IDb $db, LabelMapper $labelMapper, AclMapper $aclMapper) {
        parent::__construct($db, 'deck_boards', '\OCA\Deck\Db\Board');
        $this->labelMapper = $labelMapper;
        $this->aclMapper = $aclMapper;
    }


    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
     */
    public function find($id) {
        $sql = 'SELECT id, title, owner, color, archived FROM `*PREFIX*deck_boards` ' .
            'WHERE `id` = ?';
        $board = $this->findEntity($sql, [$id]);

        // Add labels
        $labels = $this->labelMapper->findAll($id);
        $board->setLabels($labels);

        // Add acl
        $acl = $this->aclMapper->findAll($id);
        $board->setAcl($acl);
        
        return $board;
    }

    /**
     * Find all boards for a given user
     * @param $userId
     * @param null $limit
     * @param null $offset
     * @return array
     */
    public function findAllByUser($userId, $limit=null, $offset=null) {
        $sql = 'SELECT id, title, owner, color, archived, 0 as shared FROM oc_deck_boards WHERE owner = ? UNION ' .
            'SELECT boards.id, title, owner, color, archived, 1 as shared FROM oc_deck_boards as boards ' .
            'JOIN oc_deck_board_acl as acl ON boards.id=acl.board_id WHERE acl.participant=? AND acl.type=\'user\' AND boards.owner != ?';
        $entries = $this->findEntities($sql, [$userId, $userId, $userId], $limit, $offset);
        /* @var Board $entry */
        foreach ($entries as $entry) {
            $acl = $this->aclMapper->findAll($entry->id);
            $entry->setAcl($acl);
        }
        return $entries;
    }
    /**
     * Find all boards for a given user
     * @param $groups
     * @param null $limit
     * @param null $offset
     * @return array
     */
    public function findAllByGroups($userId, $groups, $limit=null, $offset=null) {
        if(count($groups)<=0) {
            return [];
        }
        $sql = 'SELECT boards.id, title, owner, color, archived, 2 as shared FROM oc_deck_boards as boards ' .
            'INNER JOIN oc_deck_board_acl as acl ON boards.id=acl.board_id WHERE owner != ? AND type=\'group\' AND (';
        $countGroups = 0;
        foreach ($groups as $group) {
            $sql .= 'acl.participant = ? ';
            if(count($groups)>1 && $countGroups++<count($groups)-1)
                $sql .= ' OR ';
        }
        $sql .= ');';
        $entries = $this->findEntities($sql,  array_merge([$userId], $groups), $limit, $offset);
        /* @var Board $entry */
        foreach ($entries as $entry) {
            $acl = $this->aclMapper->findAll($entry->id);
            $entry->setAcl($acl);
        }
        return $entries;
    }

    public function delete(\OCP\AppFramework\Db\Entity $entity) {
        //$this->deleteRelationalEntities('label', $entity);
        return parent::delete($entity);
    }

    public function userCanView($boardId, $userInfo) {
        $board = $this->find($boardId);
        if($board->getOwner()===$userInfo['user']) {
            return true;
        }
        try {
            $sql = 'SELECT acl.* FROM oc_deck_boards as boards ' .
                'JOIN oc_deck_board_acl as acl ON boards.id=acl.board_id WHERE acl.participant=? AND acl.type=\'user\' AND boards.id = ? AND boards.owner != ?';
            $acl = $this->find($sql, [$userInfo['user'], $boardId, $userInfo['user']], $limit, $offset);
            return true;
        } catch (Exception $e) { }
        try {
            $acl = $this->find($sql, [$userInfo['user'], $boardId, $userInfo['user']], $limit, $offset);
            return true;
        } catch (Exception $e) {
        }

    }

}