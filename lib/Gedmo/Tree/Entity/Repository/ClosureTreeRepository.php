<?php

namespace Gedmo\Tree\Entity\Repository;

use Gedmo\Exception\InvalidArgumentException;
use Doctrine\ORM\Query;
use Gedmo\Mapping\ObjectManagerHelper as OMH;
use Gedmo\Tree\Entity\MappedSuperclass\AbstractClosure;
use Gedmo\Tree\Strategy;

/**
 * The ClosureTreeRepository has some useful functions
 * to interact with Closure tree. Repository uses
 * the strategy used by listener
 *
 * @author Gustavo Adrian <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
class ClosureTreeRepository extends AbstractTreeRepository
{
    /** Alias for the level value used in the subquery of the getNodesHierarchy method */
    const SUBQUERY_LEVEL = 'level';

    /**
     * {@inheritDoc}
     */
    public function getRootNodesQueryBuilder($sortByField = null, $direction = 'asc')
    {
        $meta = $this->getClassMetadata();
        $tree = $this->listener->getConfiguration($this->_em, $meta->name)->getMapping();
        $qb = $this->_em->createQueryBuilder();
        $qb->select('node')
            ->from($tree['rootClass'], 'node')
            ->where('node.'.$tree['parent']." IS NULL");

        if ($sortByField) {
            $qb->orderBy('node.'.$sortByField, strtolower($direction) === 'asc' ? 'asc' : 'desc');
        }

        return $qb;
    }

    /**
     * {@inheritDoc}
     */
    public function getRootNodesQuery($sortByField = null, $direction = 'asc')
    {
        return $this->getRootNodesQueryBuilder($sortByField, $direction)->getQuery();
    }

    /**
     * {@inheritDoc}
     */
    public function getRootNodes($sortByField = null, $direction = 'asc')
    {
        return $this->getRootNodesQuery($sortByField, $direction)->getResult();
    }

    /**
     * Get the Tree path query by given $node
     *
     * @param object $node
     *
     * @throws InvalidArgumentException - if input is not valid
     *
     * @return Query
     */
    public function getPathQuery($node)
    {
        $meta = $this->getClassMetadata();
        if (!$node instanceof $meta->name) {
            throw new InvalidArgumentException("Node is not related to this repository");
        }
        if (!$this->_em->getUnitOfWork()->isInIdentityMap($node)) {
            throw new InvalidArgumentException("Node is not managed by UnitOfWork");
        }
        $tree = $this->listener->getConfiguration($this->_em, $meta->name)->getMapping();
        $closureMeta = $this->_em->getClassMetadata($tree['closure']);

        $dql = "SELECT c, node FROM {$closureMeta->name} c";
        $dql .= " INNER JOIN c.ancestor node";
        $dql .= " WHERE c.descendant = :node";
        $dql .= " ORDER BY c.depth DESC";
        $q = $this->_em->createQuery($dql);
        $q->setParameters(compact('node'));

        return $q;
    }

    /**
     * Get the Tree path of Nodes by given $node
     *
     * @param object $node
     *
     * @return array - list of Nodes in path
     */
    public function getPath($node)
    {
        return array_map(function (AbstractClosure $closure) {
            return $closure->getAncestor();
        }, $this->getPathQuery($node)->getResult());
    }

    /**
     * @see getChildrenQueryBuilder
     */
    public function childrenQueryBuilder($node = null, $direct = false, $sortByField = null, $direction = 'ASC', $includeNode = false)
    {
        $meta = $this->getClassMetadata();
        $tree = $this->listener->getConfiguration($this->_em, $meta->name)->getMapping();

        $qb = $this->getQueryBuilder();
        if ($node !== null) {
            if ($node instanceof $meta->name) {
                if (!$this->_em->getUnitOfWork()->isInIdentityMap($node)) {
                    throw new InvalidArgumentException("Node is not managed by UnitOfWork");
                }

                $where = 'c.ancestor = :node AND ';

                $qb->select('c, node')
                    ->from($tree['closure'], 'c')
                    ->innerJoin('c.descendant', 'node');

                if ($direct) {
                    $where .= 'c.depth = 1';
                } else {
                    $where .= 'c.descendant <> :node';
                }

                $qb->where($where);

                if ($includeNode) {
                    $qb->orWhere('c.ancestor = :node AND c.descendant = :node');
                }
            } else {
                throw new \InvalidArgumentException("Node is not related to this repository");
            }
        } else {
            $qb->select('node')
                ->from($tree['rootClass'], 'node');
            if ($direct) {
                $qb->where('node.'.$tree['parent'].' IS NULL');
            }
        }

        if ($sortByField) {
            if ($meta->hasField($sortByField) && in_array(strtolower($direction), array('asc', 'desc'))) {
                $qb->orderBy('node.'.$sortByField, $direction);
            } else {
                throw new InvalidArgumentException("Invalid sort options specified: field - {$sortByField}, direction - {$direction}");
            }
        }

        if ($node) {
            $qb->setParameter('node', $node);
        }

        return $qb;
    }

    /**
     * @see getChildrenQuery
     */
    public function childrenQuery($node = null, $direct = false, $sortByField = null, $direction = 'ASC', $includeNode = false)
    {
        return $this->childrenQueryBuilder($node, $direct, $sortByField, $direction, $includeNode)->getQuery();
    }

    /**
     * @see getChildren
     */
    public function children($node = null, $direct = false, $sortByField = null, $direction = 'ASC', $includeNode = false)
    {
        $result = $this->childrenQuery($node, $direct, $sortByField, $direction, $includeNode)->getResult();
        if ($node) {
            $result = array_map(function (AbstractClosure $closure) {
                return $closure->getDescendant();
            }, $result);
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getChildrenQueryBuilder($node = null, $direct = false, $sortByField = null, $direction = 'ASC', $includeNode = false)
    {
        return $this->childrenQueryBuilder($node, $direct, $sortByField, $direction, $includeNode);
    }

    /**
     * {@inheritDoc}
     */
    public function getChildrenQuery($node = null, $direct = false, $sortByField = null, $direction = 'ASC', $includeNode = false)
    {
        return $this->childrenQuery($node, $direct, $sortByField, $direction, $includeNode);
    }

    /**
     * {@inheritDoc}
     */
    public function getChildren($node = null, $direct = false, $sortByField = null, $direction = 'ASC', $includeNode = false)
    {
        return $this->children($node, $direct, $sortByField, $direction, $includeNode);
    }

    /**
     * Removes given $node from the tree and reparents its descendants
     *
     * @todo may be improved, to issue single query on reparenting
     *
     * @param object $node
     *
     * @throws \Gedmo\Exception\InvalidArgumentException
     * @throws \Gedmo\Exception\RuntimeException         - if something fails in transaction
     */
    public function removeFromTree($node)
    {
        $meta = $this->getClassMetadata();
        if (!$node instanceof $meta->name) {
            throw new InvalidArgumentException("Node is not related to this repository");
        }
        if (!$nodeId = OMH::getIdentifier($this->_em, $node)) {
            throw new InvalidArgumentException("Node is not managed by UnitOfWork");
        }
        $tree = $this->listener->getConfiguration($this->_em, $meta->name)->getMapping();
        $pk = $meta->getSingleIdentifierFieldName();
        $parent = $meta->getReflectionProperty($tree['parent'])->getValue($node);

        $dql = "SELECT node FROM {$tree['rootClass']} node";
        $dql .= " WHERE node.{$tree['parent']} = :node";
        $q = $this->_em->createQuery($dql);
        $q->setParameters(compact('node'));
        $nodesToReparent = $q->getResult();
        // process updates in transaction
        $this->_em->getConnection()->beginTransaction();
        try {
            foreach ($nodesToReparent as $nodeToReparent) {
                $id = $meta->getReflectionProperty($pk)->getValue($nodeToReparent);
                $meta->getReflectionProperty($tree['parent'])->setValue($nodeToReparent, $parent);

                $dql = "UPDATE {$tree['rootClass']} node";
                $dql .= " SET node.{$tree['parent']} = :parent";
                $dql .= " WHERE node.{$pk} = :id";

                $q = $this->_em->createQuery($dql);
                $q->setParameters(compact('parent', 'id'));
                $q->getSingleScalarResult();

                $this->listener
                    ->getStrategy($this->_em, $meta->name)
                    ->updateNode($this->_em, $nodeToReparent, $node);

                $oid = spl_object_hash($nodeToReparent);
                $this->_em->getUnitOfWork()->setOriginalEntityProperty($oid, $tree['parent'], $parent);
            }

            $dql = "DELETE {$tree['rootClass']} node";
            $dql .= " WHERE node.{$pk} = :nodeId";

            $q = $this->_em->createQuery($dql);
            $q->setParameters(compact('nodeId'));
            $q->getSingleScalarResult();
            $this->_em->getConnection()->commit();
        } catch (\Exception $e) {
            $this->_em->close();
            $this->_em->getConnection()->rollback();
            throw new \Gedmo\Exception\RuntimeException('Transaction failed: '.$e->getMessage(), null, $e);
        }
        // remove from identity map
        $this->_em->getUnitOfWork()->removeFromIdentityMap($node);
        $node = null;
    }

    /**
     * Process nodes and produce an array with the
     * structure of the tree
     *
     * @param array - Array of nodes
     *
     * @return array - Array with tree structure
     */
    public function buildTreeArray(array $nodes)
    {
        $meta = $this->getClassMetadata();
        $tree = $this->listener->getConfiguration($this->_em, $meta->name)->getMapping();
        $nestedTree = array();
        $idField = $meta->getSingleIdentifierFieldName();
        $hasLevelProp = !empty($tree['level']);
        $levelProp = $hasLevelProp ? $tree['level'] : self::SUBQUERY_LEVEL;
        $childrenIndex = $this->repoUtils->getChildrenIndex();

        if (count($nodes) > 0) {
            $firstLevel = $hasLevelProp ? $nodes[0][0]['descendant'][$levelProp] : $nodes[0][$levelProp];
            $l = 1;     // 1 is only an initial value. We could have a tree which has a root node with any level (subtrees)
            $refs = array();

            foreach ($nodes as $n) {
                $node = $n[0]['descendant'];
                $node[$childrenIndex] = array();
                $level = $hasLevelProp ? $node[$levelProp] : $n[$levelProp];

                if ($l < $level) {
                    $l = $level;
                }

                if ($l == $firstLevel) {
                    $tmp = &$nestedTree;
                } else {
                    $tmp = &$refs[$n['parent_id']][$childrenIndex];
                }

                $key = count($tmp);
                $tmp[$key] = $node;
                $refs[$node[$idField]] = &$tmp[$key];
            }

            unset($refs);
        }

        return $nestedTree;
    }

    /**
     * {@inheritdoc}
     */
    public function getNodesHierarchy($node = null, $direct = false, array $options = array(), $includeNode = false)
    {
        return $this->getNodesHierarchyQuery($node, $direct, $options, $includeNode)->getArrayResult();
    }

    /**
     * {@inheritdoc}
     */
    public function getNodesHierarchyQuery($node = null, $direct = false, array $options = array(), $includeNode = false)
    {
        return $this->getNodesHierarchyQueryBuilder($node, $direct, $options, $includeNode)->getQuery();
    }

    /**
     * {@inheritdoc}
     */
    public function getNodesHierarchyQueryBuilder($node = null, $direct = false, array $options = array(), $includeNode = false)
    {
        $meta = $this->getClassMetadata();
        $tree = $this->listener->getConfiguration($this->_em, $meta->name)->getMapping();
        $idField = $meta->getSingleIdentifierFieldName();
        $subQuery = '';
        $hasLevelProp = isset($tree['level']) && $tree['level'];

        if (!$hasLevelProp) {
            $subQuery = ', (SELECT MAX(c2.depth) + 1 FROM '.$tree['closure'];
            $subQuery .= ' c2 WHERE c2.descendant = c.descendant GROUP BY c2.descendant) AS '.self::SUBQUERY_LEVEL;
        }

        $q = $this->_em->createQueryBuilder()
            ->select('c, node, p.'.$idField.' AS parent_id'.$subQuery)
            ->from($tree['closure'], 'c')
            ->innerJoin('c.descendant', 'node')
            ->leftJoin('node.parent', 'p')
            ->addOrderBy(($hasLevelProp ? 'node.'.$tree['level'] : self::SUBQUERY_LEVEL), 'asc');

        if ($node !== null) {
            $q->where('c.ancestor = :node');
            $q->setParameters(compact('node'));
        } else {
            $q->groupBy('c.descendant');
        }

        if (!$includeNode) {
            $q->andWhere('c.ancestor != c.descendant');
        }

        $defaultOptions = array();
        $options = array_merge($defaultOptions, $options);

        if (isset($options['childSort']) && is_array($options['childSort']) &&
            isset($options['childSort']['field']) && isset($options['childSort']['dir'])) {
            $q->addOrderBy(
                'node.'.$options['childSort']['field'],
                strtolower($options['childSort']['dir']) == 'asc' ? 'asc' : 'desc'
            );
        }

        return $q;
    }

    /**
     * {@inheritdoc}
     */
    protected function validate()
    {
        return $this->listener->getStrategy($this->_em, $this->getClassMetadata()->name)->getName() === Strategy::CLOSURE;
    }
}
