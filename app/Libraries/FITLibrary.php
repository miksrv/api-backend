<?php

class FITLibrary
{
    protected $fit_header = [];

    function create_fit_array($data)
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
        ];
    }

    /**
     * Save FIT header in database
     * @param array $data
     * @return bool
     */
    function save_fit($data = [])
    {
        $data = (empty($data) ? $this->fit_header : $data);

        if (empty($data)) return false;

        $dataModel = model('App\Models\FITsData');
        $dataModel->add_fit($data);

        return true;
    }
}