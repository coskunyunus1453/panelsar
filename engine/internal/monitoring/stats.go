package monitoring

import (
	"github.com/shirou/gopsutil/v3/cpu"
	"github.com/shirou/gopsutil/v3/disk"
	"github.com/shirou/gopsutil/v3/host"
	"github.com/shirou/gopsutil/v3/mem"
)

type SystemSnapshot struct {
	CPUUsagePercent float64 `json:"cpu_usage"`
	MemoryTotal     uint64  `json:"memory_total"`
	MemoryUsed      uint64  `json:"memory_used"`
	MemoryPercent   float64 `json:"memory_percent"`
	DiskTotal       uint64  `json:"disk_total"`
	DiskUsed        uint64  `json:"disk_used"`
	DiskPercent     float64 `json:"disk_percent"`
	Uptime          uint64  `json:"uptime"`
	Hostname        string  `json:"hostname"`
	OS              string  `json:"os"`
}

func Collect(rootPath string) SystemSnapshot {
	out := SystemSnapshot{}

	if pct, err := cpu.Percent(0, false); err == nil && len(pct) > 0 {
		out.CPUUsagePercent = pct[0]
	}

	if v, err := mem.VirtualMemory(); err == nil {
		out.MemoryTotal = v.Total
		out.MemoryUsed = v.Used
		out.MemoryPercent = v.UsedPercent
	}

	if rootPath == "" {
		rootPath = "/"
	}
	if du, err := disk.Usage(rootPath); err == nil {
		out.DiskTotal = du.Total
		out.DiskUsed = du.Used
		out.DiskPercent = du.UsedPercent
	}

	if info, err := host.Info(); err == nil {
		out.Uptime = info.Uptime
		out.Hostname = info.Hostname
		out.OS = info.OS + " " + info.PlatformVersion
	}

	return out
}
