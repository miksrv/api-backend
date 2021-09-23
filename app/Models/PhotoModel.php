<?php namespace App\Models;

use CodeIgniter\Model;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Validation\ValidationInterface;

class PhotoModel extends Model
{
    protected $table      = '';
    protected $primaryKey = 'photo_obj';

    protected $db;

    public function __construct(ConnectionInterface &$db = null, ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);

        $this->table = getenv('database.table.astro_photo');
    }

    /**
     * Return all photos array
     * @return mixed
     */
    function get_all()
    {
        return $this->db
                ->table($this->table)
                ->orderBy('photo_date', 'DESC')
                ->get()
                ->getResult();
    }

    /**
     * Return photo object by name
     * @param $name
     * @return mixed
     */
    function get_by_name($name)
    {
        return $this->db
                ->table($this->table)
                ->getWhere([$this->primaryKey => $name])
                ->getResult();
    }
}