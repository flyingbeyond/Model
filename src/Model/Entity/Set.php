<?php

namespace Model\Entity;
use Closure;
use InvalidArgumentException;
use Model\Validator\Validatable;
use Model\Validator\ValidatableInterface;
use RuntimeException;

/**
 * The class that represents a set of entities.
 * 
 * @category Entities
 * @package  Model
 * @author   Trey Shugart <treshugart@gmail.com>
 * @license  Copyright (c) 2011 Trey Shugart http://europaphp.org/license
 */
class Set implements AccessibleInterface, ValidatableInterface
{
    use Validatable;
    
    /**
     * The class used to represent each entity in the set.
     * 
     * @var string
     */
    private $class;
    
    /**
     * The data containing each entity.
     * 
     * @var array
     */
    private $data = array();
    
    /**
     * Constructs a new entity set. Primarily used for has many relations.
     * 
     * @param string $class  The class that represents the entities.
     * @param mixed  $data   The data to apply.
     * @param string $mapper The mapper to use to import the data.
     * 
     * @return Set
     */
    public function __construct($class, $data = array(), $mapper = null)
    {
        $this->class = $class;
        $this->fill($data, $mapper);
    }
    
    /**
     * Empties the current set.
     * 
     * @return Set
     */
    public function clear()
    {
        $this->data = [];
        return $this;
    }
    
    /**
     * Returns whether or not the set represents the specified class.
     * 
     * @param string $class The class name to check against.
     * 
     * @return bool
     */
    public function isRepresenting($class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }
        return $this->class === $class;
    }
    
    /**
     * Checks to see if the set represents the specified class. If not, then an exception is thrown.
     * 
     * @throws \Model\Exception If the set does not represent the specified class.
     * 
     * @param string $class The class to check.
     * 
     * @return Set
     */
    public function mustRepresent($class)
    {
        if (!$this->isRepresenting($class)) {
            $class = is_object($class) ? get_class($class) : $class;
            throw new RuntimeException(
                'The entity set is representing "'
                . $this->class
                . '" not "'
                . $class
                . '".'
            );
        }
        return $this;
    }
    
    /**
     * Fills values from a traversable item.
     * 
     * @param mixed  $data   The values to import.
     * @param string $mapper The mapper to use for importing.
     * 
     * @return Entity
     */
    public function fill($data, $mapper = null)
    {
        if (is_array($data) || is_object($data)) {
            foreach ($data as $k => $v) {
                $this->offsetSet(null, $this->ensureEntity($v, $mapper));
            }
        }
        return $this;
    }
    
    /**
     * Validates the entity and returns the error messages.
     * 
     * @return array
     */
    public function validate()
    {
        $messages   = [];
        $validators = $this->getValidators();
        
        $this->walk(function($entity) use (&$messages, $validators) {
            foreach ($validators as $message => $validator) {
                $messages = array_merge($messages, $entity->validate());
            }
        });
        
        return $messages;
    }
    
    /**
     * Converts the set to an array.
     * 
     * @param string $mapper The mapper to use for exporting.
     * 
     * @return array
     */
    public function toArray($mapper = null)
    {
        $array = array();
        
        foreach ($this as $k => $v) {
            $array[$k] = $v->toArray($mapper);
        }
        
        return $array;
    }
    
    /**
     * Executes the specified callback on each item and places the return value in an array and returns it. If $userdata
     * is passed, it is used in lieu of passing the entity as the first argument just in case a different order of
     * of parameters need to be passed in.
     * 
     * @param mixed The callback to execute on each item.
     * 
     * @return mixed
     */
    public function walk($callback)
    {
        if (!is_callable($callback)) {
            throw new InvalidArgumentException('The passed argument is not callable.');
        }
        
        foreach ($this as $entity) {
            call_user_func($callback, $entity);
        }
        
        return $this;
    }
    
    /**
     * Aggregates an array of values for the specified field.
     * 
     * @param string $field The field to aggregate values for.
     * 
     * @return array
     */
    public function aggregate($field)
    {
        $values = array();
        foreach ($this as $item) {
            $values[] = $item->__get($field);
        }
        return $values;
    }
    
    /**
     * Moves the item at the specified $currentIndex to the $newIndex and shifts any elements at the $newIndex forward.
     * 
     * @param int $currentIndex The current index.
     * @param int $newIndex     The new index.
     * 
     * @return Set
     */
    public function moveTo($currentIndex, $newIndex)
    {
        if ($item = $this->offsetGet($currentIndex)) {
            $this->offsetUnset($currentIndex);
            $this->push($newIndex, $item);
        }
        return $this;
    }
    
    /**
     * Inserts an item at the specified offset.
     * 
     * @param mixed $index The index to push the item into.
     * @param mixed $item  The item to insert.
     * 
     * @return Set
     */
    public function push($index, $item = [])
    {
        $start = array_slice($this->data, 0, $index);
        $end   = array_slice($this->data, $index);
        $item  = $this->ensureEntity($item);
        
        $this->data = array_merge($start, array($item), $end);
        
        return $this;
    }
    
    /**
     * Pulls an item from the current collection and returns it.
     * 
     * @param mixed $index The item to pull out of the current set and return.
     * 
     * @return Entity
     */
    public function pull($index)
    {
        if ($item = $this->offsetGet($index)) {
            $this->offsetUnset($index);
            return $item;
        }
        return null;
    }
    
    /**
     * Prepends an item.
     * 
     * @param mixed $item The item to prepend.
     * 
     * @return Set
     */
    public function prepend($item = [])
    {
        return $this->push(0, $item);
    }
    
    /**
     * Appends an item.
     * 
     * @param mixed $item The item to append. If set to nothing, an empty item is added.
     * 
     * @return Set
     */
    public function append($item = [])
    {
        return $this->push($this->count(), $item);
    }
    
    /**
     * Filters the set using the specified callback.
     * 
     * @param Closure $filter The filter to use.
     * 
     * @return Set
     */
    public function filter(Closure $filter)
    {
        $keys = [];
        
        foreach ($this as $k => $item) {
            if ($filter($item) !== false) {
                $keys[] = $k;
            }
        }
        
        return $this->reduce($keys);
    }
    
    /**
     * Reduces the array down to the items that match the specified array of keys. If the specified keys are empty
     * 
     * @param array $keys The keys to reduce the array down to.
     * 
     * @return Set
     */
    public function reduce($keys)
    {
        // find items that exist
        $found = array();
        
        foreach ((array) $keys as $key) {
            if (isset($this->data[$key])) {
                $found[$key] = $key;
            }
        }
        
        // if nothing is found, clear all items and return
        if (!$found) {
            return $this->clear();
        }
        
        // remove all non-existing items
        foreach ($this->data as $key => $value) {
            if (!isset($found[$key])) {
                unset($this->data[$key]);
            }
        }
        
        // re-index
        $this->data = array_values($this->data);
        
        return $this;
    }
    
    /**
     * Removes all items matching the specified query.
     * 
     * @param array $query The query of items to remove.
     * 
     * @return Set
     */
    public function remove(array $query)
    {
        // remove each item
        foreach ($this->findKeys($query) as $key) {
            unset($this->data[$key]);
        }
        
        // re-index
        $this->data = array_values($this->data);
        
        return $this;
    }
    
    /**
     * Finds items matching the specified criteria and returns a new set of them.
     * 
     * @param array $query  An array of name/value pairs of fields to match.
     * @param int   $limit  The limit of items to find.
     * @param int   $offset The offset to start looking at.
     * 
     * @return array
     */
    public function findOne(array $query)
    {
        $clone = clone $this;
        $key   = $clone->findKey($query);
        if ($key !== false) {
            return $clone->reduce($key)->offsetGet(0);
        }
        return false;
    }
    
    /**
     * Returns the first matched entity.
     * 
     * @param array $query An array of name/value pairs of fields to match.
     * 
     * @return Entity
     */
    public function find(array $query, $limit = 0, $offset = 0)
    {
        $clone = clone $this;
        return $clone->reduce($clone->findKeys($query, $limit, $offset));
    }
    
    /**
     * Returns the first matched item.
     * 
     * @param array $query An array of name/value pairs of fields to match.
     * 
     * @return Entity
     */
    public function findKey(array $query)
    {
        if ($found = $this->findKeys($query, 1)) {
            return $found[0];
        }
        return false;
    }
    
    /**
     * Finds items matching the specified criteria and returns an array of their indexes.
     * 
     * @param array $query  An array of name/value pairs of fields to match.
     * @param int   $limit  The limit of items to find.
     * @param int   $offset The offset to start looking at.
     * 
     * @return array
     */
    public function findKeys(array $query, $limit = 0, $offset = 0)
    {
        $items = array();
        foreach ($this as $key => $item) {
            if ($offset && $offset > $key) {
                continue;
            }
            
            if ($limit && $limit === count($items)) {
                break;
            }
            
            foreach ($query as $name => $value) {
                if (!preg_match('/' . str_replace('/', '\/', $value) . '/', $item->__get($name))) {
                    continue;
                }
                $items[] = $key;
            }
        }
        return $items;
    }
    
    /**
     * Returns the first element without setting the pointer to it.
     * 
     * @return Entity
     */
    public function first()
    {
        if ($this->offsetExists(0)) {
            return $this->offsetGet(0);
        }
        return null;
    }
    
    /**
     * Returns the first element without setting the pointer to it.
     * 
     * @return Entity
     */
    public function last()
    {
        $lastIndex = $this->count() - 1;
        if ($this->offsetExists($lastIndex)) {
            return $this->offsetGet($lastIndex);
        }
        return null;
    }
    
    /**
     * Adds or sets an entity in the set. The value is set directly. Only in offsetGet() is the
     * entity instantiated and the value passed to it and then re-set.
     * 
     * @param mixed $offset The offset to set.
     * @param mixed $value  The value to set.
     * 
     * @return Entity
     */
    public function offsetSet($offset, $value)
    {
        if (!$value instanceof Entity) {
            $value = $this->ensureEntity($value);
        }
        
        $offset = is_numeric($offset) ? (int) $offset : count($this->data);
        
        $this->data[$offset] = $value;
        
        return $this;
    }
    
    /**
     * Returns the entity at the specified offset if it exists. If it doesn't exist
     * then it returns null.
     * 
     * At this point, the entity is instantiated so no unnecessary overhead is used.
     * 
     * @param mixed $offset The offset to get.
     * 
     * @return mixed
     */
    public function offsetGet($offset)
    {
        if ($this->offsetExists($offset)) {
            return $this->data[$offset];
        }
        return null;
    }
    
    /**
     * Checks to make sure the specified offset exists.
     * 
     * @param mixed $offset The offset to check for.
     * 
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }
    
    /**
     * Unsets the specified item at the given offset if it exists.
     * 
     * @param mixed $offset The offset to unset.
     * 
     * @return Entity
     */
    public function offsetUnset($offset)
    {
        if ($this->offsetExists($offset)) {
            unset($this->data[$offset]);
            $this->data = array_values($this->data);
        }
        return $this;
    }
    
    /**
     * Returns the number of entities in the set.
     * 
     * @return int
     */
    public function count()
    {
        return count($this->data);
    }
    
    /**
     * Returns the current element.
     * 
     * @return Entity
     */
    public function current()
    {
        return $this->offsetGet($this->key());
    }
    
    /**
     * Returns the key of the current element.
     * 
     * @return mixed
     */
    public function key()
    {
        return key($this->data);
    }
    
    /**
     * Moves to the next element.
     * 
     * @return Entity
     */
    public function next()
    {
        next($this->data);
        return $this;
    }
    
    /**
     * Resets to the first element.
     * 
     * @return Entity
     */
    public function rewind()
    {
        reset($this->data);
        return $this;
    }
    
    /**
     * Returns whether or not another element exists.
     * 
     * @return bool
     */
    public function valid()
    {
        return !is_null($this->key());
    }
    
    /**
     * Serializes the data and returns it.
     * 
     * @return string
     */
    public function serialize()
    {
        return serialize([
            'class'      => $this->class,
            'data'       => $this->toArray(),
            'validators' => $this->validators
        ]);
    }
    
    /**
     * Unserializes and sets the specified data.
     * 
     * @param string The serialized string to unserialize and set.
     * 
     * @return void
     */
    public function unserialize($data)
    {
        $data             = unserialize($data);
        $this->class      = $data['class'];
        $this->validators = $data['validators'];
        $this->fill($data['data']);
    }
    
    /**
     * Ensures the item is a valid entity instance.
     * 
     * @param Entity $item   The entity to ensure.
     * @param string $mapper The mapper to use to import the data.
     * 
     * @return void
     */
    private function ensureEntity($item, $mapper = null)
    {
        if (!$item instanceof $this->class) {
            $item = new $this->class($item, $mapper);
        }
        return $item;
    }
}