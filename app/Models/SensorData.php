<?php namespace App\Models;

use CodeIgniter\Model;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Validation\ValidationInterface;

class SensorData extends Model
{
    protected $table = '';
    protected $db;

    public function __construct(ConnectionInterface &$db = null, ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);
    }

    /**
     * Return sensor data in period
     * @return mixed
     */
    public function get_period($source = 'meteo', $daterange = null)
    {
        $this->table = getenv('database.table.' . $source . '_data');

        return $this->db->table($this->table)
            ->where("item_timestamp BETWEEN '{$daterange->start}' AND '{$daterange->end}'")
            ->orderBy('item_timestamp', 'DESC')
            ->get()
            ->getResult();
    }

    public function clear_old_entries()
    {
        $table = getenv('database.table.astro_data');
        return $this->db->table($table)
            ->where("item_timestamp < DATE_SUB(NOW(), INTERVAL 14 DAY)")
            ->delete();
    }

    /**
     * Return sensor data by month
     * @param $month
     * @param $year
     * @return mixed
     */
    public function get_month($month, $year)
    {
        $this->table = getenv('database.table.meteo_data');

        return $this->db
            ->table($this->table)
            ->orderBy('item_timestamp', 'DESC')
            ->getWhere(['YEAR(item_timestamp)' => $year, 'MONTH(item_timestamp)' => $month])
            ->getResult();
    }
}