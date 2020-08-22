<?php
namespace Modules\Template\Models;

use App\BaseModel;
use PhpParser\Node\Expr\Cast\Object_;

class Template extends BaseModel
{
    protected $table = 'core_templates';
    protected $fillable = [
        'title',
        'content',
        'type_id',
    ];

    public static function getModelName()
    {
        return __("Template");
    }

    public static function getAsMenuItem($id)
    {
        return parent::select('id', 'title as name')->find($id);
    }

    public static function searchForMenu($q = false)
    {
        $query = static::select('id', 'title as name');
        if ($q) {
            $query->where('title', 'like', "%" . $q . "%");
        }
        $a = $query->limit(10)->get();
        return $a;
    }

    public function getEditUrlAttribute()
    {
        return url('admin/module/template/edit/' . $this->id);
    }

    public function getContentJsonAttribute()
    {
        $json = json_decode($this->content, true);
        $this->filterContentJson($json);
        return $json;
    }

    protected function filterContentJson(&$json)
    {

        if (!empty($json)) {
            foreach ($json as $k => &$item) {
                if (!isset($item['type'])) {
                    unset($json[$k]);
                    continue;
                }
                $block = $this->getBlockByType($item['type']);
                if (empty($block)) {
                    unset($json[$k]);
                    continue;
                }
                $item['is_container'] = $block['is_container'] ?? false;
                $item['component'] = $block['component'] ?? 'RegularBlock';
                if (isset($item['settings']))
                    unset($item['settings']);
                if (empty($item['model']))
                    $item['model'] = [];
                if (!empty($block['model'])) {
                    foreach ($block['model'] as $key => $val) {
                        if (!isset($item['model'][$key]))
                            $item['model'][$key] = $val;
                    }
                }
                if (!empty($item['children'])) {
                    $this->filterContentJson($item['children']);
                }
            }
        }
        $json = array_values((array)$json);
    }

    public function getBlocks()
    {
        //getBlocks
        $blocks = config('template.blocks');
        // Modules
        $custom_modules = \Modules\ServiceProvider::getModules();
        if(!empty($custom_modules)){
            foreach($custom_modules as $module){
                $moduleClass = "\\Modules\\".ucfirst($module)."\\ModuleProvider";
                if(class_exists($moduleClass))
                {
                    $blockConfig = call_user_func([$moduleClass,'getTemplateBlocks']);
                    if(!empty($blockConfig)){
                        $blocks = array_merge($blocks,$blockConfig);
                    }
                }
            }
        }
        //Custom
        $custom_modules = \Custom\ServiceProvider::getModules();
        if(!empty($custom_modules)){
            foreach($custom_modules as $module){
                $moduleClass = "\\Custom\\".ucfirst($module)."\\ModuleProvider";
                if(class_exists($moduleClass))
                {
                    $blockConfig = call_user_func([$moduleClass,'getTemplateBlocks']);
                    if(!empty($blockConfig)){
                        $blocks = array_merge($blocks,$blockConfig);
                    }
                }
            }
        }
        //Plugins
        $plugins_modules = \Plugins\ServiceProvider::getModules();
        if(!empty($plugins_modules)){
            foreach($plugins_modules as $module){
                $moduleClass = "\\Plugins\\".ucfirst($module)."\\ModuleProvider";
                if(class_exists($moduleClass))
                {
                    $blockConfig = call_user_func([$moduleClass,'getTemplateBlocks']);
                    if(!empty($blockConfig)){
                        $blocks = array_merge($blocks,$blockConfig);
                    }
                }
            }
        }

        $res = [];
        foreach ($blocks as $block => $class) {

            if (!class_exists($class))
                continue;
            $obj = new $class();
            //if(!is_subclass_of($obj,"\\Module\\Template\\Block\\BaseBlock")) continue;
            $options = $obj->options;
            $options['name'] = $obj->getName();
            $options['id'] = $block;
            $options['component'] = $obj->options['component'] ?? 'RegularBlock';
            $this->parseBlockOptions($options);
            $res[] = $options;
        }
        return $res;
    }

    public function getBlockByType($type)
    {
        $all = $this->getBlocks();
        if (!empty($all)) {
            foreach ($all as $block) {
                if ($type == $block['id'])
                    return $block;
            }
        }
        return false;
    }

    protected function parseBlockOptions(&$options)
    {

        $options['model'] = [];
        if (!empty($options['settings'])) {
            foreach ($options['settings'] as &$setting) {

                $setting['model'] = $setting['id'];
                $val = $setting['std'] ?? '';
                switch ($setting['type']) {
                    default:
                        break;
                }
                if (!empty($setting['multiple'])) {
                    $val = (array)$val;
                }
                $options['model'][$setting['id']] = $val;
            }
        }
    }

    public function getProcessedContent()
    {
        $blocks = config('template.blocks');
        // Modules
        $custom_modules = \Modules\ServiceProvider::getModules();
        if(!empty($custom_modules)){
            foreach($custom_modules as $module){
                $moduleClass = "\\Modules\\".ucfirst($module)."\\ModuleProvider";
                if(class_exists($moduleClass))
                {
                    $blockConfig = call_user_func([$moduleClass,'getTemplateBlocks']);
                    if(!empty($blockConfig)){
                        $blocks = array_merge($blocks,$blockConfig);
                    }
                }
            }
        }
        //Custom
        $custom_modules = \Custom\ServiceProvider::getModules();
        if(!empty($custom_modules)){
            foreach($custom_modules as $module){
                $moduleClass = "\\Custom\\".ucfirst($module)."\\ModuleProvider";
                if(class_exists($moduleClass))
                {
                    $blockConfig = call_user_func([$moduleClass,'getTemplateBlocks']);
                    if(!empty($blockConfig)){
                        $blocks = array_merge($blocks,$blockConfig);
                    }
                }
            }
        }
        //Plugins
        $plugins_modules = \Plugins\ServiceProvider::getModules();
        if(!empty($plugins_modules)){
            foreach($plugins_modules as $module){
                $moduleClass = "\\Plugins\\".ucfirst($module)."\\ModuleProvider";
                if(class_exists($moduleClass))
                {
                    $blockConfig = call_user_func([$moduleClass,'getTemplateBlocks']);
                    if(!empty($blockConfig)){
                        $blocks = array_merge($blocks,$blockConfig);
                    }
                }
            }
        }
        $items = json_decode($this->content, true);
        if (empty($items))
            return '';
        $html = '';
        foreach ($items as $item) {
            if (empty($item['type']))
                continue;
            if (!array_key_exists($item['type'], $blocks) or !class_exists($blocks[$item['type']]))
                continue;
            $item['model'] = isset($item['model']) ? $item['model'] : [];
            $blockModel = new $blocks[$item['type']]();
            if (method_exists($blockModel, 'content')) {
                $html .= call_user_func([
                    $blockModel,
                    'content'
                ], $item['model']);
            }
        }
        return $html;
    }
}