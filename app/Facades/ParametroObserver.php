<?php

namespace App\Observers;

use App\Models\Parametro;
use App\Helpers\ParametroHelper;
use Illuminate\Support\Facades\Cache;

class ParametroObserver
{
    public function created(Parametro $parametro): void
    {
        $this->clearRelatedCache($parametro);
    }

    public function updated(Parametro $parametro): void
    {
        $this->clearRelatedCache($parametro);
    }

    public function deleted(Parametro $parametro): void
    {
        $this->clearRelatedCache($parametro);
    }

    private function clearRelatedCache(Parametro $parametro): void
    {
        Cache::forget("parametro_{$parametro->clave}");
        Cache::forget("parametros_categoria_{$parametro->categoria}");
        Cache::forget('parametros_stats');
        Cache::forget('parametros_categorias');
    }
}
