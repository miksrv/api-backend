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
     * #TODO Optimize
     * @return mixed
     */
    public function get_period($source = 'meteo', $period = 'today')
    {
        $this->table = getenv('database.table.' . $source . '_data');

        switch ($period) {
            case 'today'     : $period = 'DATE_SUB(NOW(), INTERVAL 1 DAY)'; break;
            case 'yesterday' : $period = 'CURDATE() - INTERVAL 1 DAY'; break;
            case 'week'      : $period = 'DATE_SUB(NOW(), INTERVAL 7 DAY)'; break;
            case 'month'     : $period = 'DATE_SUB(NOW(), INTERVAL 30 DAY)'; break;
        }

        return $this->db->table($this->table)
                    ->where('`item_timestamp` >= ' . $period)
                    ->orderBy('item_timestamp', 'DESC')
                    ->get()
                    ->getResult();
    }
}