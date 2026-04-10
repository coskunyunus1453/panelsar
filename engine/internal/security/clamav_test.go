package security

import "testing"

func TestSummarizeClamScanOutput(t *testing.T) {
	out := `/var/www/x/bad.php: Html.Phishing.Account-12 FOUND
/var/www/x/clean.txt: OK

----------- SCAN SUMMARY -----------
Known viruses: 1
Engine version: 1.0.0
Scanned directories: 1
Scanned files: 2
Infected files: 1
`
	s := SummarizeClamScanOutput(out)
	if s.InfectedCount != 1 {
		t.Fatalf("count: got %d want 1", s.InfectedCount)
	}
	if len(s.InfectedFiles) != 1 || s.InfectedFiles[0] != "/var/www/x/bad.php" {
		t.Fatalf("files: %#v", s.InfectedFiles)
	}
}
