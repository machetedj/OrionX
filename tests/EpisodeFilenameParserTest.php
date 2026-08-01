<?php
declare(strict_types=1);
use App\Domain\Media\EpisodeFilenameParser;use PHPUnit\Framework\Attributes\DataProvider;use PHPUnit\Framework\TestCase;
final class EpisodeFilenameParserTest extends TestCase
{
 #[DataProvider('names')]
 public function testRecognizesCommonFormats(string $path,int $season,int $episode):void{$parsed=(new EpisodeFilenameParser())->parse($path);$this->assertNotNull($parsed);$this->assertSame($season,$parsed['season']);$this->assertSame($episode,$parsed['episode']);}
 public static function names():array{return [['/Series/Example/Season 01/Example.S01E02.mkv',1,2],['/Series/Example/Example 1x03.mp4',1,3],['/Series/Example/Season 2 Episode 4.mkv',2,4],['/Series/Example/Temporada 3 Capítulo 5.mkv',3,5]];}
 public function testReturnsNullForAmbiguousName():void{$this->assertNull((new EpisodeFilenameParser())->parse('/Series/Example/episode-final.mkv'));}
}
