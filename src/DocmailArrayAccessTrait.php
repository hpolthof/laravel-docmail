<?php namespace Hpolthof\Docmail;

trait DocmailArrayAccessTrait
{
    public function offsetExists($offset)
    {
        return isset($this->{$offset});
    }

    public function offsetGet($offset)
    {
        return $this->offsetExists($offset) ? $this->{$offset} : null;
    }

    public function offsetSet($offset, $value)
    {
        if($this->offsetExists($offset)) {
            $this->{$offset} = $value;
        } else {
            throw new DocmailException("Direct assignment of '{$offset}' is not allowed, please use the predefined setters.");
        }
    }

    public function offsetUnset($offset)
    {
        if($this->offsetExists($offset)) {
            $rc = new \ReflectionClass(static::class);
            $defaults = $rc->getDefaultProperties();
            $this->{$offset} = $defaults[$offset];
        }
    }

    public function toArray()
    {
        $rc = new \ReflectionClass($this);
        $result = [];
        foreach($rc->getProperties() as $property) {
            $result[$property->getName()] = $this->{$property->getName()};
        }
        return $result;
    }
}