<?php
declare(strict_types=1);
namespace App\Domain\Media;

final class EpisodeFilenameParser
{
    public function parse(string $path): ?array
    {
        $filename=pathinfo(str_replace('\\','/',$path),PATHINFO_FILENAME);
        $patterns=[
            '/\bS(?<season>\d{1,3})E(?<episode>\d{1,4})\b/i',
            '/\b(?<season>\d{1,3})x(?<episode>\d{1,4})\b/i',
            '/\bSeason\s*(?<season>\d{1,3})\s*Episode\s*(?<episode>\d{1,4})\b/i',
            '/\bTemporada\s*(?<season>\d{1,3})\s*(?:Cap[ií]tulo|Episodio)\s*(?<episode>\d{1,4})\b/iu',
        ];
        foreach($patterns as $pattern){
            if(!preg_match($pattern,$filename,$matches,PREG_OFFSET_CAPTURE))continue;
            $marker=$matches[0][0];$offset=$matches[0][1];
            $title=trim(preg_replace('/[._-]+/',' ',substr($filename,0,$offset))??'');
            if($title==='')$title=$this->seriesFromDirectories($path);
            return ['series_title'=>$title?:null,'season'=>(int)$matches['season'][0],'episode'=>(int)$matches['episode'][0],'special'=>(int)$matches['season'][0]===0,'marker'=>$marker];
        }
        return null;
    }
    private function seriesFromDirectories(string $path): string
    {
        $parts=explode('/',str_replace('\\','/',$path));array_pop($parts);
        foreach(array_reverse($parts) as $part)if($part!==''&&!preg_match('/^(Season|Temporada)\s*\d+$/i',$part))return trim(preg_replace('/[._-]+/',' ',$part)??'');
        return '';
    }
}
