<?php

namespace app\data\service\payment;

use app\data\service\PaymentService;
use app\data\service\UserUpgradeService;
use think\admin\Exception;
use think\admin\extend\CodeExtend;

/**
 * 账号余额支付参数处理
 * Class BalancePyamentService
 * @package app\data\service\payment
 */
class BalancePyamentService extends PaymentService
{
    /**
     * 订单信息查询
     * @param string $orderNo
     * @return array
     */
    public function query(string $orderNo): array
    {
        return [];
    }

    /**
     * 支付通知处理
     * @return string
     */
    public function notify(): string
    {
        return 'SUCCESS';
    }

    /**
     * 创建订单支付参数
     * @param string $openid 用户OPENID
     * @param string $orderNo 交易订单单号
     * @param string $paymentAmount 交易订单金额（元）
     * @param string $paymentTitle 交易订单名称
     * @param string $paymentRemark 订单订单描述
     * @param string $paymentReturn 完成回跳地址
     * @param string $paymentImage 支付凭证图片
     * @return array
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function create(string $openid, string $orderNo, string $paymentAmount, string $paymentTitle, string $paymentRemark, string $paymentReturn = '', string $paymentImage = ''): array
    {
        $order = $this->app->db->name('ShopOrder')->where(['order_no' => $orderNo])->find();
        if (empty($order)) throw new Exception("订单不存在");
        if ($order['status'] !== 2) throw new Exception("不可发起支付");
        // 创建支付行为
        $this->createPaymentAction($orderNo, $paymentTitle, $paymentAmount);
        // 扣减用户余额
        [$total, $used] = UserUpgradeService::instance()->balance($order['uid'], [$orderNo]);
        if ($paymentAmount > $total - $used) throw new Exception("可抵扣余额不足");
        $this->app->db->name('ShopOrder')->where(['order_no' => $orderNo])->update(['payment_balance' => $paymentAmount]);
        // 更新支付行为
        $this->updatePaymentAction($orderNo, CodeExtend::uniqidDate(20), $paymentAmount, '账户余额支付');
        // 刷新用户余额
        UserUpgradeService::instance()->balance($order['uid']);
        return ['info' => '余额支付完成'];
    }
}