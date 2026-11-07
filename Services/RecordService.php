<?php

namespace App\Apps\NiurenSmssend\Services;

use App\Apps\NiurenSmssend\Exceptions\SmsException;
use App\Apps\NiurenSmssend\Models\SmsRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 短信发送 · 发送记录服务
 *
 * 提供发送记录的多条件分页查询与详情读取。
 * 记录为只读数据（需求仅要求查看发送记录与发送结果），不提供编辑与删除。
 */
class RecordService
{
    /**
     * 分页查询发送记录
     *
     * @param string|null $provider  服务商筛选
     * @param string|null $status    发送状态筛选（pending/success/failed）
     * @param string|null $keyword   关键字（手机号 / 模板名称 / 模板 CODE / 调用来源）
     * @param string|null $dateFrom  起始日期（Y-m-d，含当日 00:00:00）
     * @param string|null $dateTo    结束日期（Y-m-d，含当日 23:59:59）
     * @param string|null $callerApp 调用来源筛选；传 SmsRecord::CALLER_BACKEND_FILTER 筛后台手动发送的记录
     * @param int         $pageSize  每页条数
     */
    public function paginate(
        ?string $provider = null,
        ?string $status = null,
        ?string $keyword = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $callerApp = null,
        int $pageSize = 15
    ): LengthAwarePaginator {
        $query = SmsRecord::query()
            ->ofProvider($provider)
            ->ofStatus($status)
            ->ofCallerApp($callerApp)
            ->keyword($keyword);

        $dateFrom = trim((string) $dateFrom);
        if ($dateFrom !== '') {
            $query->where('create_time', '>=', $dateFrom . ' 00:00:00');
        }

        $dateTo = trim((string) $dateTo);
        if ($dateTo !== '') {
            $query->where('create_time', '<=', $dateTo . ' 23:59:59');
        }

        return $query->orderBy('id', 'desc')->paginate($pageSize);
    }

    /**
     * 提取调用来源筛选项（出现过且非空的调用方应用标识，去重升序）
     *
     * @return array<int,string>
     */
    public function callerAppOptions(): array
    {
        return SmsRecord::query()
            ->where('caller_app', '!=', '')
            ->distinct()
            ->orderBy('caller_app')
            ->pluck('caller_app')
            ->all();
    }

    /**
     * 获取单条发送记录详情
     *
     * @throws SmsException
     */
    public function show(int $id): SmsRecord
    {
        $record = SmsRecord::find($id);

        if (! $record) {
            throw new SmsException('发送记录不存在或已被删除');
        }

        return $record;
    }
}
