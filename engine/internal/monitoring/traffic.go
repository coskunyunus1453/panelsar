package monitoring

import (
	"bufio"
	"os"
	"regexp"
	"sort"
	"strconv"
	"strings"
	"time"
)

// TrafficSummary access log örneklemesinden türetilen site trafiği özeti.
type TrafficSummary struct {
	Source            string             `json:"source"`
	LogPath           string             `json:"log_path"`
	LinesAnalyzed     int                `json:"lines_analyzed"`
	RequestsTotal     int                `json:"requests_total"`
	BytesTotal        uint64             `json:"bytes_total"`
	Status2xx         int                `json:"status_2xx"`
	Status3xx         int                `json:"status_3xx"`
	Status4xx         int                `json:"status_4xx"`
	Status5xx         int                `json:"status_5xx"`
	HourlyRequests    []TrafficHourPoint `json:"hourly_requests"`
	RequestsPerMinute float64            `json:"requests_per_minute"`
	WindowStart       *time.Time         `json:"window_start,omitempty"`
	WindowEnd         *time.Time         `json:"window_end,omitempty"`
}

// TrafficHourPoint saatlik istek sayısı (grafik için).
type TrafficHourPoint struct {
	Hour    string `json:"hour"`
	Count   int    `json:"count"`
	LabelTR string `json:"label_tr,omitempty"`
}

var reCombinedStatus = regexp.MustCompile(`"\s+(\d{3})\s+(\d+)\s+"`)
var reLogTime = regexp.MustCompile(`\[([^\]]+)\]`)

// AnalyzeSiteTraffic nginx/apache access log yolundan özet üretir.
func AnalyzeSiteTraffic(nginxPath, apachePath string, maxLines int) TrafficSummary {
	if maxLines < 100 {
		maxLines = 100
	}
	if maxLines > 20000 {
		maxLines = 20000
	}

	path := nginxPath
	src := "nginx"
	content, ok := tailFileLines(path, maxLines)
	if !ok || strings.TrimSpace(content) == "" {
		path = apachePath
		src = "apache"
		content, ok = tailFileLines(path, maxLines)
	}
	if !ok {
		return TrafficSummary{Source: "none", LogPath: path, LinesAnalyzed: 0}
	}

	return parseAccessLogContent(content, src, path)
}

func tailFileLines(path string, maxLines int) (string, bool) {
	f, err := os.Open(path)
	if err != nil {
		return "", false
	}
	defer f.Close()

	var lines []string
	sc := bufio.NewScanner(f)
	buf := make([]byte, 0, 64*1024)
	sc.Buffer(buf, 1024*1024)
	for sc.Scan() {
		lines = append(lines, sc.Text())
		if len(lines) > maxLines {
			lines = lines[1:]
		}
	}
	if err := sc.Err(); err != nil {
		return "", false
	}
	return strings.Join(lines, "\n"), true
}

func parseAccessLogContent(content, source, path string) TrafficSummary {
	lines := strings.Split(content, "\n")
	var times []time.Time
	hourBuckets := map[string]int{}
	var sumOut TrafficSummary
	sumOut.Source = source
	sumOut.LogPath = path

	for _, line := range lines {
		line = strings.TrimSpace(line)
		if line == "" {
			continue
		}
		sumOut.LinesAnalyzed++
		sumOut.RequestsTotal++

		if m := reLogTime.FindStringSubmatch(line); len(m) > 1 {
			if ts, ok := parseAccessTime(m[1]); ok {
				times = append(times, ts)
				key := ts.UTC().Format("2006-01-02 15:00")
				hourBuckets[key]++
			}
		}

		if sm := reCombinedStatus.FindStringSubmatch(line); len(sm) >= 3 {
			code := sm[1]
			if b, err := strconv.ParseUint(sm[2], 10, 64); err == nil {
				sumOut.BytesTotal += b
			}
			switch code[0] {
			case '2':
				sumOut.Status2xx++
			case '3':
				sumOut.Status3xx++
			case '4':
				sumOut.Status4xx++
			case '5':
				sumOut.Status5xx++
			}
		}
	}

	for k, v := range hourBuckets {
		sumOut.HourlyRequests = append(sumOut.HourlyRequests, TrafficHourPoint{Hour: k, Count: v})
	}
	sort.Slice(sumOut.HourlyRequests, func(i, j int) bool {
		return sumOut.HourlyRequests[i].Hour < sumOut.HourlyRequests[j].Hour
	})

	if len(times) >= 2 {
		sort.Slice(times, func(i, j int) bool { return times[i].Before(times[j]) })
		t0, t1 := times[0], times[len(times)-1]
		sumOut.WindowStart = &t0
		sumOut.WindowEnd = &t1
		dur := t1.Sub(t0).Minutes()
		if dur < 0.5 {
			dur = 0.5
		}
		sumOut.RequestsPerMinute = float64(sumOut.RequestsTotal) / dur
	} else if sumOut.RequestsTotal > 0 && sumOut.LinesAnalyzed > 0 {
		sumOut.RequestsPerMinute = float64(sumOut.RequestsTotal)
	}

	return sumOut
}

func parseAccessTime(s string) (time.Time, bool) {
	layouts := []string{
		"02/Jan/2006:15:04:05 -0700",
		"02/Jan/2006:15:04:05 +0000",
		"02/Jan/2006:15:04:05",
	}
	for _, l := range layouts {
		if t, err := time.Parse(l, s); err == nil {
			return t, true
		}
	}
	return time.Time{}, false
}
