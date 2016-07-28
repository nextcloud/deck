<?php

namespace OCA\Deck\Db;

use OCP\IDb;
use OCP\AppFramework\Db\Mapper;


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
        $sql = 'SELECT * FROM `*PREFIX*deck_boards` ' .
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
    public function findAll($userId, $limit=null, $offset=null) {
        $sql = 'SELECT * FROM `*PREFIX*deck_boards` WHERE `owner` = ?  ORDER BY `title`';
        return $this->findEntities($sql, [$userId], $limit, $offset);
    }

    public function delete(\OCP\AppFramework\Db\Entity $entity) {
        //$this->deleteRelationalEntities('label', $entity);
        return parent::delete($entity);
    }

}