<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'settings';
    protected $fillable = ['value'];

    /**
     * Grab a setting value from the database.
     *
     * @param string $key The setting key, as defined in the key db column
     *
     * @return string The setting value.
     */
    public static function get($key)
    {
        $setting = new self();
        $entry = $setting->where('key', $key)->first();

        if (!$entry) {
            return;
        }

        return $entry->value;
    }

    public static function booking_fees(){
        $setting = new self();
        $entry = $setting->where('key', 'like', 'booking_fee_%')->get();

        if (!$entry) {
            return;
        }

        $fees = array();

        foreach ($entry as $key => $setting) {
            $fees[$setting->key] = $setting->value;
        }
        return $fees;
    }
}
