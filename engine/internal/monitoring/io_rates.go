package monitoring

import (
	"strings"
	"sync"
	"time"

	"github.com/shirou/gopsutil/v3/disk"
	netutil "github.com/shirou/gopsutil/v3/net"
)

var (
	ioRateMu   sync.Mutex
	ioPrevTime time.Time
	ioPrevDisk map[string]disk.IOCountersStat
	ioPrevNet  map[string]netutil.IOCountersStat
)

func netSliceToMap(s []netutil.IOCountersStat) map[string]netutil.IOCountersStat {
	m := make(map[string]netutil.IOCountersStat, len(s))
	for _, x := range s {
		m[x.Name] = x
	}
	return m
}

func subU64(a, b uint64) uint64 {
	if a >= b {
		return a - b
	}
	return a
}

func skipNetIface(name string) bool {
	n := strings.ToLower(name)
	if n == "lo" {
		return true
	}
	if strings.HasPrefix(n, "docker") || strings.HasPrefix(n, "veth") || strings.HasPrefix(n, "br-") {
		return true
	}
	return false
}

// ComputeIORates önceki örnekle karşılaştırarak disk ve ağ throughput tahmini üretir (bloklamaz).
func ComputeIORates() (diskReadBps, diskWriteBps, netRxBps, netTxBps float64) {
	dCur, errD := disk.IOCounters()
	nSlice, errN := netutil.IOCounters(false)
	if errD != nil || errN != nil {
		return 0, 0, 0, 0
	}
	nCur := netSliceToMap(nSlice)

	ioRateMu.Lock()
	defer ioRateMu.Unlock()

	now := time.Now()
	if ioPrevTime.IsZero() || ioPrevDisk == nil || ioPrevNet == nil {
		ioPrevTime = now
		ioPrevDisk = dCur
		ioPrevNet = nCur
		return 0, 0, 0, 0
	}

	dt := now.Sub(ioPrevTime).Seconds()
	if dt < 0.05 {
		dt = 0.05
	}

	var dr, dw uint64
	for name, v := range dCur {
		if p, ok := ioPrevDisk[name]; ok {
			dr += subU64(v.ReadBytes, p.ReadBytes)
			dw += subU64(v.WriteBytes, p.WriteBytes)
		}
	}

	var rx, tx uint64
	for name, v := range nCur {
		if skipNetIface(name) {
			continue
		}
		if p, ok := ioPrevNet[name]; ok {
			rx += subU64(v.BytesRecv, p.BytesRecv)
			tx += subU64(v.BytesSent, p.BytesSent)
		}
	}

	ioPrevTime = now
	ioPrevDisk = dCur
	ioPrevNet = nCur

	return float64(dr) / dt, float64(dw) / dt, float64(rx) / dt, float64(tx) / dt
}
