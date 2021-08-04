<?php namespace App\Models;

use CodeIgniter\Model;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Validation\ValidationInterface;

class FITsData extends Model
{
    protected $table = '';
    protected $db;

    public function __construct(ConnectionInterface &$db = null, ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);

        $this->table = getenv('database.table.astro_fits');
    }

    /**
     * Add FITS file to database
     * @param $data
     * @return mixed
     */
    function add_fit($data)
    {
        return $this->db->table($this->table)->insert($data);
    }

    /**
     * Return full FITS data for create total statistic
     * @uses \App\Libraries\FITLibrary
     * @return mixed
     */
    function get_all()
    {
        return $this->db
            ->table($this->table)
            ->select('item_frame, item_exptime, item_object, item_filter')
            ->get()
            ->getResult();
    }

    /**
     * Delete FITS file in database
     * @param $id
     * @return mixed
     */
    function delete_by_id($id)
    {
        return $this->db->table($this->table)->delete(['file_id' => $id]);
    }

    /**
     * @uses \App\Libraries\FITLibrary
     * @param $name
     * @return mixed
     */
    function get_by_name($name)
    {
        return $this->db
            ->table($this->table)
            ->select('file_id, item_file_name, item_exptime, item_date_obs, 
                      item_filter, item_object, item_ccd_temp, item_offset, item_gain')
            ->orderBy('item_date_obs', 'DESC')
            ->getWhere(['item_object' => $name])
            ->getResult();
    }

    /**
     * @uses \App\Libraries\FITLibrary
     * @param $date
     * @return mixed
     */
    function get_by_date($date)
    {
        return $this->db
            ->table($this->table)
            ->select('file_id, item_file_name, item_exptime, item_date_obs, 
                      item_filter, item_object, item_ccd_temp, item_offset, item_gain')
            ->orderBy('item_date_obs', 'DESC')
            ->getWhere("DATE_FORMAT(item_date_obs, '%Y-%m-%d') = '{$date}'")
            ->getResult();
    }

    /**
     * @uses \App\Libraries\FITLibrary
     * @param $month
     * @param $year
     * @return mixed
     */
    function get_by_month($month, $year)
    {
        return $this->db
            ->table($this->table)
            ->select('item_date_obs, item_exptime')
            ->orderBy('item_date_obs', 'DESC')
            ->getWhere(['YEAR(item_date_obs)' => $year, 'MONTH(item_date_obs)' => $month])
            ->getResult();
    }


    /**
     * @uses \App\Libraries\FITLibrary
     * @param $month_period
     * @return mixed
     */
    function get_by_month_period($month_period)
    {
        return $this->db
            ->table($this->table)
            ->select('item_exptime, item_date_obs, item_object')
            ->orderBy('item_date_obs', 'DESC')
            ->getWhere("item_date_obs > DATE_SUB(NOW(), INTERVAL $month_period MONTH)")
            ->getResult();
    }
}