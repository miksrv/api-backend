<?php namespace App\Models;

use CodeIgniter\Model;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Validation\ValidationInterface;

class FITsData extends Model
{
    protected $table      = '';
    protected $primaryKey = 'file_id';
    protected $returnType = 'object';

    protected $db;

    public function __construct(ConnectionInterface &$db = null, ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);

        $this->table = getenv('database.table.astro_fits');
    }

    function add_fit($data)
    {
        return $this->db->table($this->table)->insert($data);
    }
}