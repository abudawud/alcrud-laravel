<?php

namespace AbuDawud\AlCrudLaravel\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
    public static $snakeAttributes = false;
    protected $isActiveAttribute = 'is_active';

    public static function getTableName($alias = null): string
    {
        $tableName = with(new static)->getTable();
        if (is_null($alias)) {
            return $tableName;
        } else {
            return "{$tableName} as {$alias}";
        }
    }

    public function getVisibleFields($withTable = true)
    {
        $collection = collect($this->visible)->push($this->primaryKey);
        if ($withTable) {
            return $collection->map(function ($field) {
                return "{$this->getTable()}.{$field}";
            })->toArray();
        } else {
            return $collection->toArray();
        }
    }

    public function scopeActive($query): Builder
    {
        return $query->where([
            [$this->isActiveAttribute, true],
        ]);
    }

    public function getStatusTextAttribute() {
        return $this->{$this->isActiveAttribute} == '1' ? 'Aktif' : 'Non-Aktif';
    }

    public function getStatusIconAttribute() {
        return $this->{$this->isActiveAttribute} == '1' ? '<span class="fas fa-check-circle text-primary"></span>' : '<span class="fas fa-times-circle text-danger"></span>';
    }
}
