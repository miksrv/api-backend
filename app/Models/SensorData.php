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
    public function get_period($source = 'meteo', $period = 'today', $date = null, $daterange = null)
    {
        $this->table = getenv('database.table.' . $source . '_data');

        /**
         * date - выбирается дата за день, от начала дня до его конца, или до последней записи (если текущией день)
         * date_start - начало периода, день с начала 00:00
         * date_end - концев периода, окончание дня до 23:59:59
         */

        if ($date) {
            return $this->db->table($this->table)
                ->where("DATE_FORMAT(item_timestamp, '%Y-%m-%d') = '{$date}'")
                ->orderBy('item_timestamp', 'DESC')
                ->get()
                ->getResult();
        }

        // Feature
        if ($daterange) {
            return $this->db->table($this->table)
                ->where("DATE_FORMAT(item_timestamp, '%Y-%m-%d') BETWEEN '{$daterange->start}' AND '{$daterange->end}'")
                ->orderBy('item_timestamp', 'DESC')
                ->get()
                ->getResult();

        }

        // Deprecated
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