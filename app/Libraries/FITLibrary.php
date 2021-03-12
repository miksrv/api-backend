<?php

class FITLibrary
{
    protected $fit_header = [];
    protected $_dataModel;

    // FIT file size in byte for 16 bit mask
    const FIT_FILE_SIZE = 32785920;

    function __construct()
    {
        $this->_dataModel = model('App\Models\FITsData');
    }

    function create_fit_array($data): array
    {
        return $this->fit_header = [
            'file_id'        => md5($data->FILE_NAME),
            'item_file_name' => $data->FILE_NAME,
            'item_ypixsz'    => floatval($data->YPIXSZ),
            'item_xpixsz'    => floatval($data->XPIXSZ),
            'item_naxis1'    => intval($data->NAXIS1),
            'item_naxis2'    => intval($data->NAXIS2),
            'item_naxis'     => intval($data->NAXIS),
            'item_bscale'    => intval($data->BSCALE),
            'item_simple'    => intval($data->SIMPLE),
            'item_bitpix'    => intval($data->BITPIX),
            'item_xbinning'  => intval($data->XBINNING),
            'item_ybinning'  => intval($data->YBINNING),
            'item_exptime'   => intval($data->EXPTIME),
            'item_frame'     => $data->FRAME,
            'item_aptdia'    => intval($data->APTDIA),
            'item_focallen'  => intval($data->FOCALLEN),
            'item_comment'   => $data->COMMENT,
            'item_telescop'  => $data->TELESCOP,
            'item_observer'  => $data->OBSERVER,
            'item_instrume'  => $data->INSTRUME,
            'item_pixsize1'  => floatval($data->PIXSIZE1),
            'item_pixsize2'  => floatval($data->PIXSIZE2),
            'item_ccd_temp'  => floatval($data->CCD_TEMP),
            'item_offset'    => intval($data->OFFSET),
            'item_gain'      => intval($data->GAIN),
            'item_scale'     => floatval($data->SCALE),
            'item_date_obs'  => $data->DATE_OBS,
            'item_equinox'   => $data->EQUINOX,
            'item_filter'    => $data->FILTER,
            'item_dec'       => floatval($data->DEC),
            'item_ra'        => floatval($data->RA),
            'item_object'    => $data->OBJECT,
            'item_objctdec'  => $data->OBJCTDEC,
            'item_objctra'   => $data->OBJCTRA,
            'item_sitelong'  => floatval($data->SITELONG),
            'item_sitelat'   => floatval($data->SITELAT),
            'item_bzero'     => intval($data->BZERO),
            'item_extend'    => $data->EXTEND,
            'item_airmass'   => floatval($data->AIRMASS),

            'item_hfr'   => isset($data->HFR) ? floatval($data->HFR) : NULL,
            'item_fwhm'  => isset($data->FWHM_INFO) ? floatval($data->FWHM_INFO->FWHM) : NULL,
            'item_sigma' => isset($data->FWHM_INFO) ? floatval($data->FWHM_INFO->SIGMA) : NULL,
        ];
    }

    /**
     * Delete file by ID
     * @param $fileID
     * @return bool
     */
    public function delete($fileID): bool
    {
        if (empty($fileID)) return false;

        $this->_dataModel->delete_by_id($fileID);

        return true;
    }

    /**
     * Save FIT header in database
     * @param array $data
     * @return bool
     */
    function save_fit($data = []): bool
    {
        $data = (empty($data) ? $this->fit_header : $data);

        if (empty($data)) return false;

        $this->_dataModel->add_fit($data);

        return true;
    }


    /**
     * Creates a summary array of objects and general statistics for frames, exposure
     * @return object
     */
    public function statistics(): object
    {
        helper(['transform']);

        $dataFITs = $this->_dataModel->get_all();

        $total_frame = $total_exp = 0;

        $objects  = [];
        $template = [
            'name'  => '',
            'total' => 0,
            'frame' => 0,
            'l' => 0,
            'r' => 0,
            'g' => 0,
            'b' => 0,
            'h' => 0,
            'o' => 0,
            's' => 0,
        ];
        $filter_map = [
            'Luminance' => 'l', 'Red' => 'r', 'Green' => 'g',
            'Blue' => 'b', 'Ha' => 'h', 'OIII' => 'o', 'SII' => 's'
        ];

        foreach ($dataFITs as $key => $row)
        {

            if ($row->item_frame != 'Light') {
                continue;
            }

            $total_exp   += $row->item_exptime;
            $total_frame += 1;

            $key = array_search($row->item_object, array_column($objects, 'name'));

            if ($key === false)
            {
                $_tmp = $template;
                $_tmp['name'] = $row->item_object;

                $objects[] = $_tmp;

                end($objects);

                $key = key($objects);
            }

            $objects[$key]['total'] += $row->item_exptime;
            $objects[$key]['frame'] += 1;

            $objects[$key][ $filter_map[$row->item_filter] ] += $row->item_exptime;
        }

        return (object) [
            'statistic' => $objects,
            'frames'    => $total_frame,
            'exposure'  => $total_exp,
            'filesize'  => format_bytes($total_frame * self::FIT_FILE_SIZE, 'gb'),
            'objects'   => count($objects)
        ];
    }

    function archive($month = null, $year = null): object
    {
        if (empty($month) || empty($year))
        {
            $month = date('m');
            $year  = date('Y');
        }

        $dataFITs = $this->_dataModel->get_by_month($month, $year);

        if (empty($dataFITs))
        {
            return (object)[
                'status' => false,
            ];
        }

        $_dataTmp = $result = [];

        foreach ($dataFITs as $key => $row)
        {
            $_date = date('d', strtotime($row->item_date_obs));

            if (! isset($_dataTmp[$_date]))
            {
                $_dataTmp[$_date] = [
                    'frames'   => 1,
                    'exposure' => $row->item_exptime
                ];
            }
            else
            {
                $_dataTmp[$_date]['frames']   += 1;
                $_dataTmp[$_date]['exposure'] += $row->item_exptime;
            }
        }

        foreach ($_dataTmp as $day => $row)
        {
            $date     = $day . '.' . $month . '.' . $year;
            $result[$date] = [
                'frames'   => (int) $row['frames'],
                'exposure' => (int) ($row['exposure'] / 60)
            ];
        }
        
        return (object) [
            'status'   => true,
            'data'     => $result
        ];
    }

    /**
     * Return statistic by object name
     * @param $name
     * @return object
     */
    function statistics_object($name): object
    {
        return $this->_get_statistic($this->_dataModel->get_by_name($name));
    }

    /**
     * Return statistic by shooting date
     * @param $date format (Y-m-d)
     * @return object
     */
    function statistics_day($date): object
    {
        return $this->_get_statistic($this->_dataModel->get_by_date($date));
    }

    /**
     * Create objects statistic by FIT object
     * @param $data
     * @return object
     */
    protected function _get_statistic($data): object
    {
        $total_exp = 0;

        foreach ($data as $row)
        {
            $total_exp += $row->item_exptime;
        }

        return (object) [
            'result'   => count($data) > 0,
            'data'     => $data,
            'exposure' => $total_exp,
            'filesize' => format_bytes(count($data) * self::FIT_FILE_SIZE, 'gb'),
            'frames'   => count($data)
        ];
    }
}