<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Relationships;

use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\NotImplementedException;


class ManyHasMany extends HasMany
{
	/** @var bool */
	protected $isPersisting = FALSE;


	public function persist($recursive = TRUE, & $queue = NULL)
	{
		if ($this->isPersisting) {
			return;
		}

		$this->isPersisting = TRUE;
		$toAdd = [];
		$toRemove = [];

		foreach ((array) $this->toRemove as $entity) {
			if (isset($entity->id)) {
				$toRemove[$entity->id] = $entity->id;
				unset($this->injectedValue[$entity->id]);
			}
		}

		if ($this->collection && $recursive) {
			foreach ($this->collection as $entity) {
				$this->getTargetRepository()->persist($entity, $recursive, $queue);
			}
		}

		foreach ((array) $this->toAdd as $entity) {
			if ($recursive) {
				$this->getTargetRepository()->persist($entity, $recursive, $queue);
			}
			$toAdd[$entity->id] = $entity->id;
			$this->injectedValue[$entity->id] = $entity->id;
		}

		$this->toAdd = [];
		$this->toRemove = [];
		$this->collection = NULL;

		if ($this->metadata->relationshipIsMain) {
			$relationshipMapper = $this->createCollection()->getRelationshipMapper();
			if ($toRemove) {
				$relationshipMapper->remove($this->parent, $toRemove);
			}
			if ($toAdd) {
				$relationshipMapper->add($this->parent, $toAdd);
			}
		}

		$this->isModified = FALSE;
		$this->isPersisting = FALSE;
	}


	public function getInjectedValue()
	{
		// is called only by Mapper\Memory\RMManyHasMany
		// and only if there is no unpersisted collection
		return $this->injectedValue;
	}


	public function getStorableValue()
	{
		return serialize($this->getInjectedValue());
	}


	public function getRawValue()
	{
		throw new NotImplementedException();
	}


	protected function createCollection()
	{
		if ($this->metadata->relationshipIsMain) {
			$mapperOne = $this->parent->getRepository()->getMapper();
			$mapperTwo = $this->getTargetRepository()->getMapper();
		} else {
			$mapperOne = $this->getTargetRepository()->getMapper();
			$mapperTwo = $this->parent->getRepository()->getMapper();
		}

		$collection = $mapperOne->createCollectionManyHasMany($mapperTwo, $this->metadata, $this->parent);
		return $this->applyDefaultOrder($collection);
	}


	protected function updateRelationshipAdd(IEntity $entity)
	{
		$otherSide = $entity->getProperty($this->metadata->relationshipProperty);
		$otherSide->collection = NULL;
		$otherSide->toAdd[spl_object_hash($this->parent)] = $this->parent;
	}


	protected function updateRelationshipRemove(IEntity $entity)
	{
		$otherSide = $entity->getProperty($this->metadata->relationshipProperty);
		$otherSide->collection = NULL;
		$otherSide->toRemove[spl_object_hash($this->parent)] = $this->parent;
	}

}
