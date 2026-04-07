package monitoring

import (
	"os/exec"
	"runtime"
	"sort"
	"strconv"
	"strings"

	"github.com/shirou/gopsutil/v3/cpu"
	"github.com/shirou/gopsutil/v3/disk"
	"github.com/shirou/gopsutil/v3/load"
	"github.com/shirou/gopsutil/v3/mem"
	"github.com/shirou/gopsutil/v3/process"
)

// ProcessTop süreç özeti (panel dashboard).
type ProcessTop struct {
	PID        int32   `json:"pid"`
	Name       string  `json:"name"`
	CPUPercent float64 `json:"cpu_percent"`
	RSSBytes   uint64  `json:"rss_bytes"`
}

// MountUsage disk bölümü kullanımı.
type MountUsage struct {
	Path       string  `json:"path"`
	Fstype     string  `json:"fstype,omitempty"`
	UsedPct    float64 `json:"used_percent"`
	UsedBytes  uint64  `json:"used_bytes"`
	TotalBytes uint64  `json:"total_bytes"`
}

// ExtendedSnapshot Collect + CPU/RAM/disk detay ve top listeler.
type ExtendedSnapshot struct {
	SystemSnapshot
	CPUModel             string       `json:"cpu_model"`
	CPUCoresLogical      int          `json:"cpu_cores_logical"`
	MemoryAvailable      uint64       `json:"memory_available"`
	SwapTotal            uint64       `json:"swap_total"`
	SwapUsed             uint64       `json:"swap_used"`
	SwapPercent          float64      `json:"swap_percent"`
	Load1                float64      `json:"load1"`
	Load5                float64      `json:"load5"`
	Load15               float64      `json:"load15"`
	DiskReadBytesPerSec  float64      `json:"disk_read_bytes_per_sec"`
	DiskWriteBytesPerSec float64      `json:"disk_write_bytes_per_sec"`
	NetRxBytesPerSec     float64      `json:"net_rx_bytes_per_sec"`
	NetTxBytesPerSec     float64      `json:"net_tx_bytes_per_sec"`
	TopCPUProcesses      []ProcessTop `json:"top_cpu_processes"`
	TopMemoryProcesses   []ProcessTop `json:"top_memory_processes"`
	TopDiskMounts        []MountUsage `json:"top_disk_mounts"`
}

func skipMountFstype(fs string) bool {
	switch strings.ToLower(fs) {
	case "tmpfs", "devtmpfs", "proc", "sysfs", "cgroup2", "cgroup", "overlay", "squashfs", "autofs", "rpc_pipefs", "configfs", "debugfs", "tracefs", "securityfs", "pstore", "bpf", "fusectl":
		return true
	default:
		return false
	}
}

// CollectExtended tam snapshot (dashboard detayları için).
func CollectExtended(rootPath string) ExtendedSnapshot {
	base := Collect(rootPath)
	out := ExtendedSnapshot{SystemSnapshot: base}

	if infos, err := cpu.Info(); err == nil && len(infos) > 0 {
		out.CPUModel = strings.TrimSpace(infos[0].ModelName)
	}
	if n, err := cpu.Counts(true); err == nil {
		out.CPUCoresLogical = n
	}
	if v, err := mem.VirtualMemory(); err == nil {
		out.MemoryAvailable = v.Available
	}
	if s, err := mem.SwapMemory(); err == nil {
		out.SwapTotal = s.Total
		out.SwapUsed = s.Used
		out.SwapPercent = s.UsedPercent
	}
	if avg, err := load.Avg(); err == nil && avg != nil {
		out.Load1 = avg.Load1
		out.Load5 = avg.Load5
		out.Load15 = avg.Load15
	}
	dr, dw, rx, tx := ComputeIORates()
	out.DiskReadBytesPerSec = dr
	out.DiskWriteBytesPerSec = dw
	out.NetRxBytesPerSec = rx
	out.NetTxBytesPerSec = tx

	out.TopCPUProcesses = topCPUProcesses(3)
	out.TopMemoryProcesses = topMemoryProcesses(3)
	out.TopDiskMounts = topDiskMountsByUsage(3, rootPath)

	return out
}

func topDiskMountsByUsage(limit int, preferRoot string) []MountUsage {
	if limit < 1 {
		limit = 3
	}
	if preferRoot == "" {
		preferRoot = "/"
	}
	parts, err := disk.Partitions(false)
	if err != nil {
		return nil
	}
	var rows []MountUsage
	seen := map[string]struct{}{}
	for _, p := range parts {
		if skipMountFstype(p.Fstype) {
			continue
		}
		mp := p.Mountpoint
		if mp == "" {
			continue
		}
		if _, ok := seen[mp]; ok {
			continue
		}
		u, err := disk.Usage(mp)
		if err != nil || u.Total < 64*1024*1024 {
			continue
		}
		seen[mp] = struct{}{}
		rows = append(rows, MountUsage{
			Path:       mp,
			Fstype:     p.Fstype,
			UsedPct:    u.UsedPercent,
			UsedBytes:  u.Used,
			TotalBytes: u.Total,
		})
	}
	sort.Slice(rows, func(i, j int) bool {
		return rows[i].UsedPct > rows[j].UsedPct
	})
	if len(rows) > limit {
		rows = rows[:limit]
	}
	return rows
}

func topCPUProcesses(limit int) []ProcessTop {
	if limit < 1 {
		limit = 3
	}
	if runtime.GOOS == "linux" {
		if rows := psTopLinux("pcpu", limit); len(rows) > 0 {
			return rows
		}
	}
	return topCPUProcessesGopsutil(limit)
}

func topMemoryProcesses(limit int) []ProcessTop {
	if limit < 1 {
		limit = 3
	}
	if runtime.GOOS == "linux" {
		if rows := psTopLinux("rss", limit); len(rows) > 0 {
			return rows
		}
	}
	return topMemoryProcessesGopsutil(limit)
}

// psTopLinux sort key: pcpu | rss (GNU ps).
func psTopLinux(sortKey string, limit int) []ProcessTop {
	args := []string{"-eo", "pid=,pcpu=,rss=,comm=", "--sort=-" + sortKey}
	cmd := exec.Command("ps", args...)
	out, err := cmd.Output()
	if err != nil {
		return nil
	}
	lines := strings.Split(strings.TrimSpace(string(out)), "\n")
	var res []ProcessTop
	for _, line := range lines {
		line = strings.TrimSpace(line)
		if line == "" {
			continue
		}
		pt := parsePsLine(line)
		if pt == nil || pt.Name == "" {
			continue
		}
		res = append(res, *pt)
		if len(res) >= limit {
			break
		}
	}
	return res
}

func parsePsLine(line string) *ProcessTop {
	fields := strings.Fields(line)
	if len(fields) < 4 {
		return nil
	}
	pid, err1 := strconv.ParseInt(fields[0], 10, 32)
	pcpu, err2 := strconv.ParseFloat(fields[1], 64)
	rssKb, err3 := strconv.ParseUint(fields[2], 10, 64)
	if err1 != nil || err2 != nil || err3 != nil {
		return nil
	}
	name := strings.Join(fields[3:], " ")
	if len(name) > 120 {
		name = name[:117] + "…"
	}
	return &ProcessTop{
		PID:        int32(pid),
		Name:       name,
		CPUPercent: pcpu,
		RSSBytes:   rssKb * 1024,
	}
}

func topCPUProcessesGopsutil(limit int) []ProcessTop {
	procs, err := process.Processes()
	if err != nil {
		return nil
	}
	type row struct {
		p   *process.Process
		cpu float64
		rss uint64
		nm  string
	}
	var rows []row
	for _, p := range procs {
		cpuP, err := p.CPUPercent()
		if err != nil {
			continue
		}
		nm, _ := p.Name()
		var rss uint64
		if mi, _ := p.MemoryInfo(); mi != nil {
			rss = mi.RSS
		}
		rows = append(rows, row{p: p, cpu: cpuP, rss: rss, nm: nm})
	}
	sort.Slice(rows, func(i, j int) bool {
		return rows[i].cpu > rows[j].cpu
	})
	var out []ProcessTop
	for i := 0; i < len(rows) && i < limit; i++ {
		out = append(out, ProcessTop{
			PID:        rows[i].p.Pid,
			Name:       rows[i].nm,
			CPUPercent: rows[i].cpu,
			RSSBytes:   rows[i].rss,
		})
	}
	return out
}

func topMemoryProcessesGopsutil(limit int) []ProcessTop {
	procs, err := process.Processes()
	if err != nil {
		return nil
	}
	type row struct {
		p   *process.Process
		rss uint64
		nm  string
		cpu float64
	}
	var rows []row
	for _, p := range procs {
		mi, err := p.MemoryInfo()
		if err != nil || mi == nil {
			continue
		}
		nm, _ := p.Name()
		cpuP, _ := p.CPUPercent()
		rows = append(rows, row{p: p, rss: mi.RSS, nm: nm, cpu: cpuP})
	}
	sort.Slice(rows, func(i, j int) bool {
		return rows[i].rss > rows[j].rss
	})
	var out []ProcessTop
	for i := 0; i < len(rows) && i < limit; i++ {
		out = append(out, ProcessTop{
			PID:        rows[i].p.Pid,
			Name:       rows[i].nm,
			CPUPercent: rows[i].cpu,
			RSSBytes:   rows[i].rss,
		})
	}
	return out
}
