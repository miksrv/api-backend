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
     * NEW
     * @return mixed
     */
    function get_count()
    {
        return $this->db
            ->table('astro_photo_new')
            ->countAll();
    }

    /**
     * NEW
     * @param int $limit
     * @return mixed
     */
    function get_list(int $limit = 0)
    {
        $build = $this->db
            ->table('astro_photo_new')
            ->select(['photo_obj', 'photo_title', 'photo_text', 'photo_date', 'photo_category', 'photo_file', 'photo_file_ext'])
            ->orderBy('photo_date', 'DESC');

        if ($limit !== 0) $build->limit($limit);

        return $build->get()->getResult();
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