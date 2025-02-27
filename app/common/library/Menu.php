<?php

namespace app\common\library;

use app\admin\model\MenuRule;
use think\db\exception\DbException;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;

/**
 * 后台菜单类
 */
class Menu
{
    /**
     * @param array      $menu
     * @param int|string $parent 父级规则name或id
     * @param string     $mode   添加模式(规则重复时):cover=覆盖旧菜单,rename=重命名新菜单,ignore=忽略
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public static function create(array $menu, $parent = 0, string $mode = 'cover')
    {
        $pid        = 0;
        $parentRule = MenuRule::where((is_numeric($parent) ? 'id' : 'name'), $parent)->find();
        if ($parentRule) {
            $pid = $parentRule['id'];
        }
        foreach ($menu as $item) {
            if (!self::requiredAttrCheck($item)) {
                continue;
            }

            // 属性
            $item['status'] = '1';
            if (!isset($item['pid']) && $pid) {
                $item['pid'] = $pid;
            }

            $oldMenu = MenuRule::where('name', $item['name'])->find();
            if ($oldMenu) {
                // 存在相关名称的菜单规则
                if ($mode == 'cover') {
                    $oldMenu->save($item);
                } elseif ($mode == 'rename') {
                    $count         = MenuRule::where('name', $item['name'])->count();
                    $item['name']  = $item['name'] . '-conflicting-' . $count;
                    $item['title'] = $item['title'] . '-conflicting-' . $count;
                    $oldMenu       = MenuRule::create($item);
                } elseif ($mode == 'ignore') {
                    $oldMenu = $menu;
                }
            } else {
                $oldMenu = MenuRule::create($item);
            }
            if (isset($item['children']) && $item['children']) {
                self::create($item['children'], $oldMenu['name'], $mode);
            }
        }
    }

    /**
     * 删菜单
     * @param string|int $id        规则name或id
     * @param bool       $recursion 是否递归删除子级菜单、是否删除自身，是否删除上级空菜单
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public static function delete($id, bool $recursion = false): bool
    {
        if (!$id) {
            return true;
        }
        $menuRule = MenuRule::where((is_numeric($id) ? 'id' : 'name'), $id)->find();
        if (!$menuRule) {
            return true;
        }

        $children = MenuRule::where('pid', $menuRule['id'])->select()->toArray();
        if ($recursion && $children) {
            foreach ($children as $child) {
                self::delete($child['id'], true);
            }
        }

        if (!$children || $recursion) {
            $menuRule->delete();
            self::delete($menuRule->pid);
        }
        return true;
    }

    /**
     * 启用菜单
     * @param string|int $id 规则name或id
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public static function enable($id): bool
    {
        $menuRule = MenuRule::where((is_numeric($id) ? 'id' : 'name'), $id)->find();
        if (!$menuRule) {
            return false;
        }
        $menuRule->status = '1';
        $menuRule->save();
        return true;
    }

    /**
     * 禁用菜单
     * @param string|int $id 规则name或id
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public static function disable($id): bool
    {
        $menuRule = MenuRule::where((is_numeric($id) ? 'id' : 'name'), $id)->find();
        if (!$menuRule) {
            return false;
        }
        $menuRule->status = '0';
        $menuRule->save();
        return true;
    }

    public static function requiredAttrCheck($menu): bool
    {
        $attrs = ['type', 'title', 'name'];
        foreach ($attrs as $attr) {
            if (!array_key_exists($attr, $menu)) {
                return false;
            }
            if (!$menu[$attr]) {
                return false;
            }
        }
        return true;
    }
}