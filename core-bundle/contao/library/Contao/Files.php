use Symfony\Component\Filesystem\Exception\IOException;
		try
			// Unix fix: rename case sensitively
			if (strcasecmp($strOldName, $strNewName) === 0 && strcmp($strOldName, $strNewName) !== 0)
			{
				$fs->rename($this->strRootDir . '/' . $strOldName, $this->strRootDir . '/' . $strOldName . '__', true);
				$strOldName .= '__';
			}
			$fs->rename($this->strRootDir . '/' . $strOldName, $this->strRootDir . '/' . $strNewName, true);
		}
		catch (IOException)
		{
			return false;
		}
		try
		{
			(new Filesystem())->remove($this->strRootDir . '/' . $strFile);
		}
		catch (IOException)
		{
			return false;
		}

		return true;